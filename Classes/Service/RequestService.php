<?php

declare(strict_types=1);

namespace TRAW\PowermailSalesforce\Service;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Http\RequestFactory;

class RequestService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(private readonly RequestFactory $requestFactory)
    {
    }

    public function sendDataToSalesforce(string $targetUrl, array $data): void
    {
        $response = $this->requestFactory->request(
            $targetUrl,
            'POST',
            [
                'form_params' => $data,
            ]
        );

        if ($response->getStatusCode() !== 200) {
            $this->logger?->error('Web-to-Lead request failed', [
                'statusCode' => $response->getStatusCode(),
                'targetUrl' => $targetUrl,
                'body' => $response->getBody()->getContents(),
            ]);
        }
    }

    public function sendJsonToSalesforce(string $url, array $data, string $accessToken): array
    {
        $this->logger?->debug('sendJsonToSalesforce()', $data);

        $response = $this->requestFactory->request(
            $url,
            'POST',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'http_errors' => false,
                'json' => $data,
            ]
        );

        $statusCode = $response->getStatusCode();
        $body = json_decode($response->getBody()->getContents(), true) ?? [];

        if ($statusCode < 200 || $statusCode >= 300) {
            $this->logger?->error('Web-to-Case REST API request failed', [
                'statusCode' => $statusCode,
                'url' => $url,
                'response' => $body,
            ]);
            throw new \RuntimeException(
                'Salesforce REST API request failed. HTTP ' . $statusCode,
                1711446010
            );
        }

        $this->logger?->info('Salesforce Case created successfully', ['response' => $body]);

        return $body;
    }

    public function uploadFileToSalesforce(
        string $url,
        string $caseId,
        string $filePath,
        string $fileName,
        string $accessToken,
    ): void {
        $this->logger?->debug('uploadFileToSalesforce()', [$caseId, $filePath, $fileName]);

        $entityContent = json_encode([
            'Title' => $fileName,
            'PathOnClient' => $fileName,
            'FirstPublishLocationId' => $caseId,
        ]);

        $response = $this->requestFactory->request(
            $url,
            'POST',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
                'http_errors' => false,
                'multipart' => [
                    [
                        'name' => 'entity_content',
                        'contents' => $entityContent,
                        'headers' => [
                            'Content-Type' => 'application/json',
                        ],
                    ],
                    [
                        'name' => 'VersionData',
                        'contents' => fopen($filePath, 'r'),
                        'filename' => $fileName,
                    ],
                ],
            ]
        );

        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            $this->logger?->error('Salesforce file upload failed', [
                'statusCode' => $statusCode,
                'caseId' => $caseId,
                'fileName' => $fileName,
                'body' => $response->getBody()->getContents(),
            ]);
            throw new \RuntimeException(
                'Salesforce file upload failed. HTTP ' . $statusCode,
                1711446011
            );
        }

        $this->logger?->info('Salesforce file uploaded successfully', [
            'caseId' => $caseId,
            'fileName' => $fileName,
        ]);
    }
}
