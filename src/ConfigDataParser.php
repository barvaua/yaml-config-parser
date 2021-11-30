<?php

declare(strict_types=1);

namespace YamlConfig;

use Symfony\Component\Yaml\Yaml;

class ConfigDataParser
{
    private const CONFIG_FILE_EXTENSIONS = [
        '.yaml',
        '.yml',
    ];

    private string $configDir;

    /**
     * @param string $configDir
     */
    public function __construct(string $configDir)
    {
        $this->configDir = $configDir;
    }

    /**
     * @param string $fileName
     * @return mixed
     */
    public function parseConfigYamlFile(string $fileName)
    {
        $file = null;

        foreach (self::CONFIG_FILE_EXTENSIONS as $extension) {
            $path = $this->configDir . $fileName . $extension;

            if (is_file($path)) {
                $file = $path;
                break;
            }
        }

        if (null === $file) {
            throw new LogicException('Not yaml file!');
        }

        return Yaml::parseFile($file);
    }

    /**
     * @param string $fileName
     * @param array|string $params
     * @param bool $onlyFirstMatch
     * @param bool $withKeys
     * @return array|array[]|mixed
     */
    public function findByParams(string $fileName, $params, bool $onlyFirstMatch = true, bool $withKeys = false)
    {
        return $this->searchMatches($this->parseConfigYamlFile($fileName), $params, $onlyFirstMatch, $withKeys);
    }

    /**
     * @param array $cfgData
     * @param array|string $params
     * @param bool $onlyFirstMatch
     * @param bool $withKeys
     * @return array|array[]|mixed
     */
    public function searchMatches(array $cfgData, $params, bool $onlyFirstMatch, bool $withKeys = false)
    {
        if (empty($params)) {
            return null;
        }

        if (!is_array($params)) {
            return $cfgData[$params] ?? null;
        }

        $matches = null;
        $links = $cfgData['links'] ?? [];

        foreach ($links as $link) {
            if (!isset($link['k'])) {
                continue;
            }

            $found = true;

            foreach ($link['k'] as $key => $value) {
                $invert = false; // For values negation.

                if (isset($value['NOT'])) {
                    $value = $value['NOT'];
                    $invert = true;
                }

                $found = $this->areCoincidences($params, $key, $value);

                if ($invert == true) {
                    $found = !$found;
                }

                if (!$found) {
                    break;
                }
            }

            if ($found) {
                if ($onlyFirstMatch) {
                    return $withKeys ? $link : $link['v'];
                }

                if (null === $matches) {
                    $matches = $withKeys ? [$link] : [$link['v']];
                    continue;
                }

                $matches[] = $withKeys ? $link : $link['v'];
            }
        }

        if (null === $matches && isset($cfgData['def'])) {
            if ($onlyFirstMatch) {
                return $withKeys ? ['k' => 'def', 'v' => $cfgData['def']] : $cfgData['def'];
            }

            return $withKeys ? [['k' => 'def', 'v' => $cfgData['def']]] : [$cfgData['def']];
        }

        return $matches;
    }

    /**
     * @param array $params
     * @param string $key
     * @param $value
     * @return bool
     */
    private function areCoincidences(array $params, string $key, $value): bool
    {
        if (!isset($params[$key])) {
            return false;
        }

        if (is_array($value)) {
            if (isset($value['from']) && isset($value['to'])) {
                return ($params[$key] >= $value['from'] && $params[$key] <= $value['to']);
            } elseif (isset($value['from'])) {
                return ($params[$key] >= $value['from']);
            } elseif (isset($value['to'])) {
                return ($params[$key] <= $value['to']);
            } else {
                return is_array($params[$key])
                    ? !empty(array_intersect($value, $params[$key]))
                    : in_array($params[$key], $value);
            }
        }

        return is_array($params[$key]) ? in_array($value, $params[$key]) : ($params[$key] === $value);
    }
}
