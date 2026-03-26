<?php

declare(strict_types=1);

namespace TRAW\PowermailSalesforce\Finisher;

use In2code\Powermail\Domain\Model\Mail;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TRAW\PowermailSalesforce\Service\DataCollectionService;
use TRAW\PowermailSalesforce\Service\OAuthService;
use TRAW\PowermailSalesforce\Service\RequestService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

class SalesforceFinisher extends \In2code\Powermail\Finisher\AbstractFinisher implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected RequestService $salesforceRequestService;
    protected DataCollectionService $dataCollectionService;
    protected OAuthService $oAuthService;

    public function __construct(
        Mail                  $mail,
        array                 $configuration,
        array                 $settings,
        bool                  $formSubmitted,
        string                $actionMethodName,
        ContentObjectRenderer $contentObject,
    ) {
        parent::__construct($mail, $configuration, $settings, $formSubmitted, $actionMethodName, $contentObject);

        $this->salesforceRequestService = GeneralUtility::makeInstance(RequestService::class);
        $this->dataCollectionService = GeneralUtility::makeInstance(DataCollectionService::class, $mail, $configuration);
        $this->oAuthService = GeneralUtility::makeInstance(OAuthService::class);
    }

    public function salesforceDataFinisher(): void
    {
        $formProperties = $this->dataCollectionService->getSalesforceFormProperties($this->getMail()->getForm());
        if ($formProperties['enable'] === false || !$this->getMail()->getAnswers()->count()) {
            return;
        }

        $mode = $formProperties['mode'] ?? 'web2lead';

        if ($mode === 'web2case') {
            $this->handleWebToCase($formProperties);
        } else {
            $this->handleWebToLead($formProperties);
        }
    }

    private function handleWebToLead(array $formProperties): void
    {
        if (empty($this->configuration['targetUrl']) || empty($formProperties['oid'])) {
            return;
        }

        // @extensionScannerIgnoreLine
        $contentObjectData = $this->contentObject->data;

        if (!empty($this->settings['thx']['redirect'])) {
            $returnPageUid = $this->settings['thx']['redirect'];
        } elseif (!empty($this->settings['main']['returnPageUid'])) {
            $returnPageUid = $this->settings['main']['returnPageUid'];
        } else {
            $returnPageUid = $contentObjectData['pid'];
        }

        $returnUrl = $this->contentObject->typoLink_URL([
            'parameter' => $returnPageUid,
            'additionalParams' => '&L=' . $contentObjectData['sys_language_uid'] ?? 0,
            'section' => empty($this->settings['main']['returnPageUid']) ? $contentObjectData['uid'] : '',
            'forceAbsoluteUrl' => true,
        ]);

        $data = $this->dataCollectionService->collectDataFromPowermailAnswers([
            'oid' => $formProperties['oid'],
            'retURL' => $returnUrl,
        ]);

        $requestUrl = $this->configuration['debug']['enable'] ? $this->configuration['debug']['targetUrl'] : $this->configuration['targetUrl'];
        if (str_starts_with($requestUrl, 'https://webto.salesforce.com') === true) {
            $requestUrl = $this->dataCollectionService->ensureOrgIdInUrl($requestUrl, $data['oid']);
        }

        $this->salesforceRequestService->sendDataToSalesforce($requestUrl, $data);
    }

    private function handleWebToCase(array $formProperties): void
    {
        $web2caseConfig = $this->configuration['web2case'] ?? [];

        if (empty($web2caseConfig['instanceUrl']) || empty($web2caseConfig['clientId']) || empty($web2caseConfig['clientSecret'])) {
            $this->logger?->error('Web-to-Case configuration incomplete: instanceUrl, clientId, and clientSecret are required.');
            return;
        }

        $instanceUrl = rtrim($web2caseConfig['instanceUrl'], '/');
        $apiVersion = $web2caseConfig['apiVersion'] ?? 'v64.0';

        // Obtain OAuth2 access token
        $accessToken = $this->oAuthService->getAccessToken(
            $instanceUrl,
            $web2caseConfig['clientId'],
            $web2caseConfig['clientSecret'],
        );

        // Build Case data
        $defaults = [
            'Status' => 'New',
            'Origin' => 'Web',
        ];

        if (!empty($formProperties['recordTypeId'])) {
            $defaults['RecordTypeId'] = $formProperties['recordTypeId'];
        }

        $data = $this->dataCollectionService->collectDataForWebToCase($defaults);

        // Create Case
        $caseUrl = $instanceUrl . '/services/data/' . $apiVersion . '/sobjects/Case';

        try {
            $caseResponse = $this->salesforceRequestService->sendJsonToSalesforce($caseUrl, $data, $accessToken);
        } catch (\RuntimeException) {
            $caseResponse = null;
        }

        $caseId = $caseResponse['id'] ?? null;
        if (empty($caseId)) {
            $this->logger?->error('Salesforce Case creation did not return an ID', ['response' => $caseResponse]);
            return;
        }

        // Upload file attachments
        $files = $this->dataCollectionService->extractFileFields();
        if (!empty($files)) {
            $contentVersionUrl = $instanceUrl . '/services/data/' . $apiVersion . '/sobjects/ContentVersion';
            foreach ($files as $file) {
                try {
                    $this->salesforceRequestService->uploadFileToSalesforce(
                        $contentVersionUrl,
                        $caseId,
                        $file['path'],
                        $file['name'],
                        $accessToken,
                    );
                } catch (\RuntimeException) {
                }
            }
        }
    }
}
