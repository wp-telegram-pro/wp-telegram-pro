<?php

namespace wptelegrampro;
/**
 * WPTelegramPro SSL Certificate
 *
 * @link       https://wordpress.org/plugins/wp-telegram-pro
 * @since      1.0.0
 * @copyright  Based on https://github.com/spatie/ssl-certificate
 *
 * @package    WPTelegramPro
 * @subpackage WPTelegramPro/modules/inc
 */
class SSLCertificateWPTP
{
    private $port = 443, $host = null, $timeOut = 30, $response = null, $rawResponse = null;

    public function __construct($host = null)
    {
        $this->setHost($host);
        return $this;
    }

    public function request()
    {
        if ($this->host === null)
            throw new \Exception('Your host address is null!');

        $client = false;
        $streamContext = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
            ],
        ]);
        try {
            $client = stream_socket_client(
                "ssl://{$this->host}:{$this->port}",
                $errorNumber,
                $errorDescription,
                $this->timeOut,
                STREAM_CLIENT_CONNECT,
                $streamContext
            );
        } catch (Throwable $thrown) {
            $this->handleRequestFailure($this->host, $thrown);
        }

        if (!$client)
            throw new \Exception("Could not download certificate for host `{$this->host}` because Could not connect to `{$this->host}");

        $this->rawResponse = stream_context_get_params($client);
        return $this;
    }

    /**
     * Parse response
     *
     * @return boolean|array
     */
    public function response()
    {
        if (isset($this->rawResponse['options']['ssl']['peer_certificate'])) {
            $this->rawResponse = openssl_x509_parse($this->rawResponse['options']['ssl']['peer_certificate']);
            $this->response = array(
                'validFromDate' => $this->validFromDate(),
                'expirationDate' => $this->expirationDate(),
                'isExpired' => $this->isExpired(),
                'isSelfSigned' => $this->isSelfSigned(),
                'signatureAlgorithm' => $this->getSignatureAlgorithm(),
                'additionalDomains' => $this->getAdditionalDomains(),
                'daysUntilExpirationDate' => $this->daysUntilExpirationDate(),
                'issuer' => $this->getIssuer(),
                'isValid' => $this->isValid(),
            );
            return $this->response;
        }
        return false;
    }

    public function setHost($url)
    {
        if ($url === null)
            return $this;

        $this->host = HelpersWPTP::getURLHost($url);
        return $this;
    }

    public function setPort($port)
    {
        $this->port = $port;
        return $this;
    }

    public function setTimeOut($timeOut)
    {
        $this->timeOut = $timeOut;
        return $this;
    }

    public function isValid(string $url = null)
    {
        if (!(time() > $this->rawResponse['validFrom_time_t'] && time() < $this->rawResponse['validTo_time_t'])) {
            return false;
        }

        if (!empty($url)) {
            $url = isset($url) || is_null($url) ? $this->getDomain() : $url;
            return $this->appliesToUrl($url);
        }

        return true;
    }

    public function getDomain()
    {
        if (!array_key_exists('CN', $this->rawResponse['subject'])) {
            return '';
        }

        if (is_string($this->rawResponse['subject']['CN'])) {
            return $this->rawResponse['subject']['CN'];
        }

        if (is_array($this->rawResponse['subject']['CN'])) {
            return $this->rawResponse['subject']['CN'][0];
        }

        return '';
    }

    public function getDomains()
    {
        $allDomains = $this->getAdditionalDomains();
        $allDomains[] = $this->getDomain();
        $uniqueDomains = array_unique($allDomains);

        return array_values(array_filter($uniqueDomains));
    }

    public function getRawResponse()
    {
        return $this->rawResponse;
    }

    public function validFromDate()
    {
        return date("Y-m-d H:i:s", $this->rawResponse['validFrom_time_t']);
    }

    public function expirationDate()
    {
        return date("Y-m-d H:i:s", $this->rawResponse['validTo_time_t']);
    }

    public function isExpired()
    {
        return time() > $this->rawResponse['validTo_time_t'];
    }

    public function getIssuer()
    {
        return isset($this->rawResponse['issuer']['CN']) ? $this->rawResponse['issuer']['CN'] : '';
    }

    public function isSelfSigned()
    {
        return $this->getIssuer() === $this->getDomain();
    }

    public function getSignatureAlgorithm()
    {
        return isset($this->rawResponse['signatureTypeSN']) ? $this->rawResponse['signatureTypeSN'] : '';
    }

    public function getAdditionalDomains()
    {
        $additionalDomains = explode(', ', isset($this->rawResponse['extensions']['subjectAltName']) ? $this->rawResponse['extensions']['subjectAltName'] : '');

        return array_map(function (string $domain) {
            return str_replace('DNS:', '', $domain);
        }, $additionalDomains);
    }

    public function daysUntilExpirationDate()
    {
        $today = date_create(date("Y-m-d"));
        $validTo = date_create(date("Y-m-d", $this->rawResponse['validTo_time_t']));
        $diff = date_diff($today, $validTo);
        return $diff->format("%a");
    }

    public function appliesToUrl($url)
    {
        if (filter_var($url, FILTER_VALIDATE_IP)) {
            $host = $url;
        } else {
            $host = $this->parseURL($url);
        }

        $certificateHosts = $this->getDomains();

        foreach ($certificateHosts as $certificateHost) {
            $certificateHost = str_replace('ip address:', '', strtolower($certificateHost));
            if ($host === $certificateHost) {
                return true;
            }

            if ($this->wildcardHostCoversHost($certificateHost, $host)) {
                return true;
            }
        }

        return false;
    }

    protected function wildcardHostCoversHost(string $wildcardHost, string $host)
    {
        if ($host === $wildcardHost) {
            return true;
        }

        if (!HelpersWPTP::startsWith($wildcardHost, '*')) {
            return false;
        }

        if (substr_count($wildcardHost, '.') < substr_count($host, '.')) {
            return false;
        }

        $wildcardHostWithoutWildcard = HelpersWPTP::substr($wildcardHost, 1);

        $hostWithDottedPrefix = ".{$host}";

        return HelpersWPTP::endsWith($hostWithDottedPrefix, $wildcardHostWithoutWildcard);
    }

    protected function handleRequestFailure(string $hostName, Throwable $thrown)
    {
        if (HelpersWPTP::strContains($thrown->getMessage(), 'getaddrinfo failed')) {
            throw new \Exception("The host named `{$hostName}` does not exist.");
        }

        if (HelpersWPTP::strContains($thrown->getMessage(), 'error:14090086')) {
            throw new \Exception("Could not find a certificate on  host named `{$hostName}`.");
        }

        throw new \Exception("Could not download certificate for host `{$hostName}` because {$thrown->getMessage()}");
    }
}
