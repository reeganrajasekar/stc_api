<?php

namespace Utils;

// System
use Slim\Psr7\Response;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class Helper
{
    public static function getLogger(string $channel = 'app'): Logger
    {
        $logDirectory = __DIR__  . '/../../logs';

        if (!file_exists($logDirectory)) {
            mkdir($logDirectory, 0777, true); 
        }

        $date = date('Y-m-d');
        $logFile = "$logDirectory/$channel-$date.log";

        $logger = new Logger($channel);
        $logger->pushHandler(new StreamHandler($logFile, Logger::DEBUG));

        return $logger;
    }

    public static function jsonResponse($data = null, int $status = 200): Response
    {
        $response = new Response();
        $response->getBody()->write(json_encode([
            'success' => $status >= 200 && $status < 300,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    public static function errorResponse(string $message, int $status = 400): Response
    {
        $response = new Response();
        $response->getBody()->write(json_encode([
            'success' => false,
            'error' => $message,
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    public static function castArrayOfObjects(array $items, array $schema): array
    {
        return array_map(function ($item) use ($schema) {
            return self::castObject($item, $schema);
        }, $items);
    }

    public static function parseOfflineEvents(string $log): array
    {
        $parts = array_map('trim', explode(',', $log));
        $events = [];

        if (empty($parts[0]) || !is_numeric($parts[0])) {
            return []; // invalid format
        }

        $count = (int)$parts[0];
        $expectedLength = 1 + ($count * 4);

        if (count($parts) < $expectedLength) {
            return [];
        }

        for ($i = 1; $i < $expectedLength; $i += 4) {
            $epoch = (int)($parts[$i + 1] ?? 0);

            $events[] = [
                'id'    => (int)($parts[$i] ?? 0),
                'time'    => $epoch > 0 ? date('Y-m-d H:i:s', $epoch) : null,
                'type'   => (int)($parts[$i + 2] ?? 1),
                'index_id'   => (string)($parts[$i + 3] ?? 0),
            ];
        }

        return $events;
    }


    public static function castObject(array $data, array $schema): array
    {
        $casted = [];

        foreach ($schema as $key => $type) {
            if (!array_key_exists($key, $data)) {
                $casted[$key] = null;
                continue;
            }

            $value = $data[$key];

            if ($value === null) {
                $casted[$key] = null;
                continue;
            }

            $types = explode('|', $type);

            foreach ($types as $t) {
                switch (trim($t)) {
                    case 'int':
                        $casted[$key] = (int)$value;
                        continue 3;
                    case 'float':
                        $casted[$key] = (float)$value;
                        continue 3;
                    case 'bool':
                        $casted[$key] = (bool)$value;
                        continue 3;
                    case 'string':
                        $casted[$key] = (string)$value;
                        continue 3;
                    case 'array':
                        $casted[$key] = (array)$value;
                        continue 3;
                    case 'null':
                        continue 2;
                }
            }
            $casted[$key] = $value;
        }
        return $casted;
    }

    public static function validateInput($input, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleString) {
            $ruleParts = explode('|', $ruleString);
            $value = $input[$field] ?? null;
            $isRequired = in_array('required', $ruleParts);

            // Required check
            if ($isRequired && !isset($input[$field])) {
                $errors[$field] = 'Field is required';
                continue;
            }

            // Skip validation for non-required missing fields
            if (!$isRequired && !isset($input[$field])) {
                continue;
            }

            foreach ($ruleParts as $rule) {
                if (strpos($rule, ':') !== false) {
                    [$ruleName, $param] = explode(':', $rule);
                } else {
                    $ruleName = $rule;
                    $param = null;
                }

                switch ($ruleName) {
                    case 'int':
                        if (!is_numeric($value)) {
                            $errors[$field] = 'is must be an number!';
                        }
                        break;
                    case 'string':
                        if (!is_string($value)) {
                            $errors[$field] = 'is must be a text!';
                        }
                        break;
                    case 'email':
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field] = 'is invalid format!';
                        }
                        break;
                    case 'min':
                        if (strlen($value) < (int)$param) {
                            $errors[$field] = "should have atleast $param letters";
                        }
                        break;
                    case 'max':
                        if (strlen($value) > (int)$param) {
                            $errors[$field] = "should have maximum $param letters";
                        }
                        break;
                    case 'in':
                        $options = explode(',', $param);
                        if (!in_array($value, $options)) {
                            $errors[$field] = "should be one of: " . implode(', ', $options);
                        }
                        break;
                }
            }
        }
        return $errors;
    }
}
