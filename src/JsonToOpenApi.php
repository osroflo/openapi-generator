<?php

namespace Openapi\Generator;

use Symfony\Component\Yaml\Yaml;
use cebe\openapi\Reader;

class JsonToOpenApi
{
    public $data = [];
    public $openapiData = [];
    private $includeExampleKey = true;
    private $includeDescriptionKey = false;

    /**
     * for model purposes there is no reason to have definition of same object many times
     */
    private $limit = [
        'segments',
    ];

    private $dummyValues = [
        'stopAirports' => [
            'locationCode' => 'DEN',
            'arrivalDateTime' => '2019-01-16T11:13:00',
            'departureDateTime' => '2019-01-16T14:00:00',
            'elapsedTime' => '268',
            'duration' => '268',
            'equipment' => '738',
        ],
    ];

    private function is_multidimensional(array $array): bool
    {
        return is_integer(array_key_first($array));
    }

    public function isArrayAllKeyString($InputArray): bool
    {
        if (!is_array($InputArray)) {
            return false;
        }

        if (count($InputArray) <= 0) {
            return true;
        }

        return array_unique(array_map("is_string", array_keys($InputArray))) === array(true);
    }

    public function isObject(array $inputArray = []): bool
    {
        return !empty($inputArray) && $this->isArrayAllKeyString($inputArray);
    }

    public function loopPropertiesRecursive(array $data, &$general): void
    {
        foreach ($data as $key => $value) {

            // check if it is array
            if (is_array($value)) {

                if (empty($value)) {
                    $value = $this->getDummyData($key);
                }
                // ---------------------------------
                // logic to keep only one
                // ---------------------------------
                $limit = in_array($key, $this->limit);

                if ($limit) {
                    $value = array_slice($value, 0, 1, true);
                }
                // ---------------------------------

                if ($this->isObject($value)) {
                    $type = 'object';
                    $additionalPropertyKey = 'properties';
                } else {
                    $type = 'array';
                    $additionalPropertyKey = 'items';
                }

                $general[$key] = [
                    'type' => $type,
                    $additionalPropertyKey => null
                ];

                $this->loopPropertiesRecursive($value, $general[$key][$additionalPropertyKey]);
            } else {
                $this->addAsProperty($general, $key, $value);
            }
        }
    }

    private function includeExample(array &$data, $key, $value)
    {
        if ($this->includeExampleKey) {
            $data[$key]['example'] = $value;
        }
    }

    private function includeDescription(array &$data, $key)
    {
        if ($this->includeDescriptionKey) {
            $data[$key]['description'] = '';
        }
    }

    public function includeFormat(array &$data, $key, $type)
    {
        $format = null;

        if ($type === 'number') {
            $format = 'double';
        }

        if ($format) {
            $data[$key]['format'] = $format;
        }
    }

    public function toYaml(array $data): string
    {
        return Yaml::dump($data);
    }

    public function toJson(array $data): string
    {
        return json_encode($data);
    }

    public function validateJson(string $data): bool
    {
        $openapi = Reader::readFromJson($data);
        return $openapi->validate();
    }

    /**
     * OpenAPI only accepts certain data types
     * @param string $type
     * @return string
     */
    public function fixDataTypeToValid(string $type): string
    {
        $fixedType = $type;

        if ($type === 'double') {
            $fixedType = 'number';
        }

        return $fixedType;
    }

    public function addAsProperty(&$general, $key, $value)
    {
        $type = $this->fixDataTypeToValid(gettype($value));

        $general[$key] = [
            'type' => $type,
            // 'description' =>
            // 'example' => $value,
        ];

        $this->includeExample($general, $key, $value);
        $this->includeFormat($general, $key, $type);
        $this->includeDescription($general, $key);
    }

    public function fix(&$data)
    {
        foreach ($data as $key => &$value) {

            // check keys with empty arrays
            if (empty($value)) {
                unset($data[$key]);
            }

            if (is_array($value)) {

                if ($this->is_multidimensional($value)) {
                    $value = current($value);
                }

                $this->fix($value);
            }
        }
    }

    public function getDummyData($key)
    {
        return $this->dummyValues[$key] ?? ['dummy'];
    }
}
