<?php

declare(strict_types=1);

namespace TRAW\PowermailSalesforce\Service;

use In2code\Powermail\Domain\Model\Answer;
use In2code\Powermail\Domain\Model\Mail;
use TRAW\PowermailSalesforce\Domain\Model\Field;
use TRAW\PowermailSalesforce\Domain\Model\Form;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

class DataCollectionService
{
    private readonly ConnectionPool $connectionPool;

    public function __construct(
        private readonly Mail  $mail,
        private readonly array $configuration,
    ) {
        $this->connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
    }

    public function collectDataFromPowermailAnswers(array $data): array
    {
        if ($this->configuration['debug']['enable']) {
            $data['debug'] = '1';
            $data['debugEmail'] = $this->configuration['debug']['email'];
        }

        $this->extractAnswerData($this->mail->getAnswers(), $data);

        //concatenate multiselect value with ;
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = implode(';', $value);
            }
        }

        return $data;
    }

    public function collectDataForWebToCase(array $defaults): array
    {
        $data = $defaults;

        $this->extractAnswerData($this->mail->getAnswers(), $data, true);

        // concatenate multiselect values with ;
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = implode(';', $value);
            }
        }

        // don't include empty fields, for validation on SalesForce
        // will fail for empty strings for date fields
        $data = array_filter($data);

        return $data;
    }

    public function extractFileFields(): array
    {
        $files = [];
        $uploadFolder = $this->getUploadFolder();

        foreach ($this->mail->getAnswers() as $answer) {
            /** @var Answer $answer */
            if ($answer->getValueType() !== Answer::VALUE_TYPE_UPLOAD) {
                continue;
            }

            $value = $answer->getValue();
            $fileNames = is_array($value) ? $value : [$value];

            foreach ($fileNames as $fileName) {
                if (empty($fileName)) {
                    continue;
                }
                $filePath = GeneralUtility::getFileAbsFileName($uploadFolder . $fileName);
                if (file_exists($filePath)) {
                    $files[] = [
                        'path' => $filePath,
                        'name' => $fileName,
                    ];
                }
            }
        }

        return $files;
    }

    public function getSalesforceFormProperties($form): array
    {
        if (ExtensionManagementUtility::isLoaded('extender')) {
            /** @var Form $form */
            return $form->getSfFormProperties();
        } else {
            $formProperties = $this->getFieldFromDb($form, ['sf_oid', 'sf_enable', 'sf_mode', 'sf_record_type_id']);

            return [
                'enable' => (bool)($formProperties['sf_enable'] ?? false),
                'oid' => $formProperties['sf_oid'] ?? null,
                'mode' => $formProperties['sf_mode'] ?? 'web2lead',
                'recordTypeId' => $formProperties['sf_record_type_id'] ?? '',
            ];
        }
    }

    private function extractAnswerData(array|ObjectStorage $answers, array &$data, bool $skipFiles = false): void
    {
        foreach ($answers as $answer) {
            /** @var Answer $answer */
            if ($skipFiles && $answer->getValueType() === Answer::VALUE_TYPE_UPLOAD) {
                continue;
            }

            /** @var Field $field */
            $field = $answer->getField();
            if (ExtensionManagementUtility::isLoaded('extender')) {
                $sfFieldName = $field->getSfFieldname();
            } else {
                $sfFieldName = $this->getFieldFromDb($field, 'sf_fieldname');
            }

            if (!empty($sfFieldName)) {
                if ($answer->getValueType() === Answer::VALUE_TYPE_DATE) {
                    // getValue() would process the unix timestamp via
                    // LocalizationUtility::translate('datepicker_format_date')
                    // so we work with the raw value instead!
                    $value = (int)$answer->getRawValue();
                    $value = match($field->getDatepickerSettings()) {
                        'date' => date('Y-m-d', $value),
                        'datetime' => date('Y-m-d h:i A', $value),
                        'time' => date('h:i A', $value),
                        default => $value
                    };
                } else {
                    $value = $answer->getValue();
                }
                if ($sfFieldName === 'email' || $field->isSenderEmail()) {
                    $value = strtolower($value);
                }
                $data[$sfFieldName] = $value;
            }
        }
    }

    public function getFieldFromDb($object, string|array $fieldNames): bool|array|string
    {
        $tableName = null;

        if (
            is_a($object, \TRAW\PowermailSalesforce\Domain\Model\Form::class)
            || is_a($object, \In2code\Powermail\Domain\Model\Form::class)
        ) {
            $tableName = 'tx_powermail_domain_model_form';
        } elseif (
            is_a($object, \TRAW\PowermailSalesforce\Domain\Model\Field::class)
            || is_a($object, \In2code\Powermail\Domain\Model\Field::class)
        ) {
            $tableName = 'tx_powermail_domain_model_field';
        } else {
            return false;
        }

        $result = $this->connectionPool->getConnectionForTable($tableName)
            ->select(is_array($fieldNames) ? $fieldNames : [$fieldNames], $tableName, ['uid' => $object->getUid()], [], [], 1);

        if (is_array($fieldNames)) {
            return $result->fetchAssociative();
        }
        return $result->fetchOne();
    }

    public function ensureOrgIdInUrl(string $url, string|int $orgId): string
    {
        $parts = parse_url($url);

        $query = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        if (!array_key_exists('orgId', $query)) {
            $query['orgId'] = $orgId;
        }

        $parts['query'] = http_build_query($query);

        return (isset($parts['scheme']) ? "{$parts['scheme']}://" : '')
            . ($parts['host'] ?? '')
            . ($parts['path'] ?? '')
            . (!empty($parts['query']) ? "?{$parts['query']}" : '')
            . (isset($parts['fragment']) ? "#{$parts['fragment']}" : '');
    }

    private function getUploadFolder(): string
    {
        return $this->configuration['misc']['uploadFolder'] ?? 'uploads/tx_powermail/';
    }
}
