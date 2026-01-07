<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ServiceDeskClient
{
    private $config;

    public function __construct(ServiceDeskConfigService $service)
    {
        $companyId = auth()->user()->company_id;
        $this->config = $service->activeForCompany($companyId);
    }

    private function baseUri(): string
    {
        return rtrim($this->config->base_url, '/') .
            '/api/' . $this->config->api_version;
    }

    private function headers(): array
    {
        return [
            'TECHNICIAN_KEY' => $this->config->technician_key,
            'Accept' => 'application/json',
            'Cookie' =>
                'SDPSESSIONID=' . $this->config->session_id . '; ' .
                '_zcsr_tmp=' . $this->config->csrf_token . '; ' .
                'sdpcsrfcookie=' . $this->config->csrf_token,
        ];
    }

    public function get(string $uri)
    {
        return Http::withOptions([
                'verify' => $this->config->verify_ssl,
                'timeout' => $this->config->timeout,
            ])
            ->withHeaders($this->headers())
            ->get($this->baseUri() . $uri);
    }

    public function put(string $uri, array $payload)
    {
        return Http::withOptions([
                'verify' => $this->config->verify_ssl,
                'timeout' => $this->config->timeout,
            ])
            ->withHeaders($this->headers())
            ->asForm()
            ->put($this->baseUri() . $uri, [
                'input_data' => json_encode($payload),
            ]);
    }
}
