<?php

namespace App\Domains\Requests\Services;

use App\Domains\Environments\Models\Environment;
use App\Domains\Requests\Models\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RequestExecutionService
{
    protected Client $client;

    public function __construct()
    {
        $this->client = Http::withOptions([
            'timeout' => 30,
            'http_errors' => false,
            'verify' => false, // For local development testing
        ])->buildClient();
    }

    /**
     * Execute an arbitrary HTTP request based on input payload.
     * This acts as the proxy for the frontend.
     */
    public function resolve(array $payload, ?string $environmentId = null): array
    {
        $method = strtoupper($payload['method'] ?? 'GET');
        $url = $payload['url'] ?? '';

        if (empty($url)) {
            throw new \InvalidArgumentException('URL cannot be empty.');
        }

        $pathVariables = $payload['path_variables'] ?? [];
        if (! empty($pathVariables)) {
            foreach ($pathVariables as $pv) {
                if (! empty($pv['key']) && isset($pv['enabled']) && $pv['enabled']) {
                    $url = str_replace(':'.$pv['key'], $pv['value'] ?? '', $url);
                }
            }
        }

        $variables = $this->getEnvironmentVariables($environmentId);

        $url = $this->interpolate($url, $variables);

        $query = $this->parseKeyValuePairs($payload['query_params'] ?? [], $variables);
        $headers = $this->parseKeyValuePairs($payload['headers'] ?? [], $variables);

        $body = $payload['body'] ?? null;
        $options = [
            'query' => $query,
            'headers' => $headers,
        ];

        $auth = $payload['auth'] ?? null;
        if ($auth && isset($auth['type']) && $auth['type'] !== 'noauth') {
            $type = strtolower($auth['type']);
            if ($type === 'bearer' && ! empty($auth['bearerToken'])) {
                $token = $this->interpolate($auth['bearerToken'], $variables);
                $options['headers']['Authorization'] = 'Bearer '.$token;
            } elseif ($type === 'basic' && (isset($auth['basicUsername']) || isset($auth['basicPassword']))) {
                $username = $this->interpolate($auth['basicUsername'] ?? '', $variables);
                $password = $this->interpolate($auth['basicPassword'] ?? '', $variables);
                $options['auth'] = [$username, $password];
            } elseif ($type === 'apikey' && ! empty($auth['apiKeyKey'])) {
                $key = $this->interpolate($auth['apiKeyKey'], $variables);
                $value = $this->interpolate($auth['apiKeyValue'] ?? '', $variables);
                $addTo = $auth['apiKeyAddTo'] ?? 'header';
                if ($addTo === 'header') {
                    $options['headers'][$key] = $value;
                } else {
                    $options['query'][$key] = $value;
                }
            }
        }

        if (! empty($body)) {
            $decodedBody = is_array($body) ? $body : json_decode($body, true);
            if (is_array($decodedBody) && isset($decodedBody['mode'])) {
                $bodyMode = $decodedBody['mode'];

                if ($bodyMode === 'raw') {
                    $rawContent = $decodedBody['raw']['content'] ?? '';
                    $rawContent = $this->interpolate($rawContent, $variables);
                    $options['body'] = $rawContent;

                    $rawLang = $decodedBody['raw']['language'] ?? 'text';
                    if (! isset($options['headers']['Content-Type'])) {
                        if ($rawLang === 'json') {
                            $options['headers']['Content-Type'] = 'application/json';
                        } elseif ($rawLang === 'javascript') {
                            $options['headers']['Content-Type'] = 'application/javascript';
                        } elseif ($rawLang === 'html') {
                            $options['headers']['Content-Type'] = 'text/html';
                        } elseif ($rawLang === 'xml') {
                            $options['headers']['Content-Type'] = 'application/xml';
                        }
                    }
                } elseif ($bodyMode === 'urlencoded' || $bodyMode === 'x-www-form-urlencoded') {
                    $formParams = [];
                    if (isset($decodedBody['urlencoded']) && is_array($decodedBody['urlencoded'])) {
                        foreach ($decodedBody['urlencoded'] as $item) {
                            if (! empty($item['key']) && isset($item['enabled']) && $item['enabled']) {
                                $k = $this->interpolate($item['key'], $variables);
                                $v = $this->interpolate($item['value'] ?? '', $variables);
                                $dataType = strtolower($item['dataType'] ?? '');
                                if ($dataType === 'boolean' || strtolower((string) $v) === 'true' || strtolower((string) $v) === 'false') {
                                    $lowerV = strtolower((string) $v);
                                    if (in_array($lowerV, ['true', '1', 'yes', 'on'], true)) {
                                        $v = '1';
                                    } elseif (in_array($lowerV, ['false', '0', 'no', 'off', ''], true)) {
                                        $v = '0';
                                    }
                                }
                                $formParams[$k] = $v;
                            }
                        }
                    }
                    if (! empty($formParams)) {
                        $options['form_params'] = $formParams;
                    }
                } elseif ($bodyMode === 'formdata' || $bodyMode === 'form-data') {
                    $multipart = [];
                    if (isset($decodedBody['formdata']) && is_array($decodedBody['formdata'])) {
                        foreach ($decodedBody['formdata'] as $item) {
                            if (! empty($item['key']) && isset($item['enabled']) && $item['enabled']) {
                                $k = $this->interpolate($item['key'], $variables);
                                $v = $this->interpolate($item['value'] ?? '', $variables);
                                $dataType = strtolower($item['dataType'] ?? '');
                                if ($dataType === 'boolean' || strtolower((string) $v) === 'true' || strtolower((string) $v) === 'false') {
                                    $lowerV = strtolower((string) $v);
                                    if (in_array($lowerV, ['true', '1', 'yes', 'on'], true)) {
                                        $v = '1';
                                    } elseif (in_array($lowerV, ['false', '0', 'no', 'off', ''], true)) {
                                        $v = '0';
                                    }
                                }
                                $multipart[] = [
                                    'name' => $k,
                                    'contents' => $v,
                                ];
                            }
                        }
                    }
                    if (! empty($multipart)) {
                        $options['multipart'] = $multipart;
                    }
                } elseif ($bodyMode === 'graphql') {
                    $query = $decodedBody['graphql']['query'] ?? '';
                    $variablesStr = $decodedBody['graphql']['variables'] ?? '{}';

                    $query = $this->interpolate($query, $variables);
                    $variablesStr = $this->interpolate($variablesStr, $variables);

                    $varsDecoded = json_decode($variablesStr, true) ?? [];

                    $options['json'] = [
                        'query' => $query,
                        'variables' => $varsDecoded,
                    ];
                }
            } else {
                $options['body'] = is_string($body) ? $this->interpolate($body, $variables) : json_encode($body);
            }
        }

        return [
            'resolved_url' => $url,
            'method' => $method,
            'request_options' => $options,
        ];
    }

    public function execute(array $payload, ?string $environmentId = null): array
    {
        $resolved = $this->resolve($payload, $environmentId);
        $method = $resolved['method'];
        $url = $resolved['resolved_url'];
        $options = $resolved['request_options'];

        $startTime = microtime(true);

        try {
            $response = $this->client->request($method, $url, $options);
            $endTime = microtime(true);

            return array_merge($this->formatResponse($response, $endTime - $startTime), [
                'resolved_url' => $url,
                'request_options' => $options,
            ]);
        } catch (RequestException $e) {
            $endTime = microtime(true);
            $response = $e->getResponse();

            if ($response) {
                return array_merge($this->formatResponse($response, $endTime - $startTime), [
                    'resolved_url' => $url,
                    'request_options' => $options,
                ]);
            }

            return [
                'status' => 0,
                'status_text' => 'Error',
                'headers' => [],
                'body' => $e->getMessage(),
                'time_ms' => round(($endTime - $startTime) * 1000),
                'size_bytes' => 0,
                'resolved_url' => $url,
                'request_options' => $options,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 0,
                'status_text' => 'Exception',
                'headers' => [],
                'body' => $e->getMessage(),
                'time_ms' => 0,
                'size_bytes' => 0,
                'resolved_url' => $url,
                'request_options' => $options,
            ];
        }
    }

    protected function formatResponse(Response $response, float $duration): array
    {
        $body = (string) $response->getBody();
        $size = strlen($body);

        // Try to parse JSON for pretty-printing in the frontend
        $isJson = Str::contains($response->getHeaderLine('Content-Type'), 'application/json');

        return [
            'status' => $response->getStatusCode(),
            'status_text' => $response->getReasonPhrase(),
            'headers' => $response->getHeaders(),
            'body' => $body,
            'is_json' => $isJson,
            'time_ms' => round($duration * 1000),
            'size_bytes' => $size,
        ];
    }

    protected function parseKeyValuePairs(array $items, array $variables): array
    {
        $result = [];
        foreach ($items as $item) {
            if (! empty($item['key']) && isset($item['enabled']) && $item['enabled']) {
                $key = $this->interpolate($item['key'], $variables);
                $value = $this->interpolate($item['value'] ?? '', $variables);
                $result[$key] = $value;
            }
        }

        return $result;
    }

    protected function getEnvironmentVariables(?string $environmentId): array
    {
        if (! $environmentId) {
            return [];
        }

        $environment = Environment::with('variables')->find($environmentId);

        if (! $environment) {
            return [];
        }

        return $environment->variables->where('enabled', true)->pluck('value', 'key')->toArray();
    }

    protected function interpolate(string $content, array $variables): string
    {
        if (empty($variables) || empty($content)) {
            return $content;
        }

        // Find {{variable_name}} or {variable_name} and replace with value from $variables
        return preg_replace_callback('/\{\{\s*([^}]+?)\s*\}\}|\{\s*([^}]+?)\s*\}/', function ($matches) use ($variables) {
            $key = trim(! empty($matches[1]) ? $matches[1] : $matches[2]);

            return $variables[$key] ?? $matches[0];
        }, $content);
    }
}
