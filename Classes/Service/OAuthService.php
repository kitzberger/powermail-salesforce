<?php

declare(strict_types=1);

namespace TRAW\PowermailSalesforce\Service;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Http\RequestFactory;

class OAuthService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const CACHE_IDENTIFIER = 'runtime';
    private const CACHE_KEY_PREFIX = 'powermailsalesforce_oauth_';

    public function __construct(
        private readonly RequestFactory $requestFactory,
        private readonly CacheManager $cacheManager,
    ) {}

    public function getAccessToken(string $instanceUrl, string $clientId, string $clientSecret): string
    {
        $cacheKey = self::CACHE_KEY_PREFIX . md5($instanceUrl . $clientId);
        $cache = $this->cacheManager->getCache(self::CACHE_IDENTIFIER);

        $cachedToken = $cache->get($cacheKey);
        if ($cachedToken !== false) {
            return $cachedToken;
        }

        $tokenUrl = rtrim($instanceUrl, '/') . '/services/oauth2/token';

        $response = $this->requestFactory->request(
            $tokenUrl,
            'POST',
            [
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                ],
            ]
        );

        if ($response->getStatusCode() !== 200) {
            $this->logger?->error('Salesforce OAuth2 token request failed', [
                'statusCode' => $response->getStatusCode(),
                'body' => $response->getBody()->getContents(),
            ]);
            throw new \RuntimeException(
                'Failed to obtain Salesforce OAuth2 access token. HTTP ' . $response->getStatusCode(),
                1711446000
            );
        }

        $body = json_decode($response->getBody()->getContents(), true);
        if (empty($body['access_token'])) {
            $this->logger?->error('Salesforce OAuth2 response missing access_token', ['body' => $body]);
            throw new \RuntimeException(
                'Salesforce OAuth2 response did not contain an access token.',
                1711446001
            );
        }

        $accessToken = $body['access_token'];
        $cache->set($cacheKey, $accessToken);

        $this->logger?->info('Salesforce OAuth2 access token obtained successfully');

        return $accessToken;
    }
}
