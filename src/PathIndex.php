<?php
namespace Openapi\Generator;

use Symfony\Component\Yaml\Yaml;
use cebe\openapi\Reader;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Class PathIndex
 * Generate open api definition files from paths/_index.yaml
 *
 * @package Openapi\Generator
 */
class PathIndex
{
    public $paths = [];
    public $currentWorkingDirectory;
    private $climate;

    public function setCurrentWorkingDirectory(string $currentWorkingDirectory): void
    {
        $this->currentWorkingDirectory = $currentWorkingDirectory;
    }

    public function setClimate($climate): void
    {
        $this->climate = $climate;
    }

    // read path index
    public function loadFile(string $fileName): void
    {
        $this->paths = Yaml::parseFile($fileName);

        if (empty($this->paths)) {
            $this->climate->error(
                sprintf('=> The file: %s does not have any paths. Make sure to add at least one path.', $fileName)
            );
            die(1);
        }
    }

    // find new paths
    public function findNewPaths()
    {
        foreach($this->paths as $path => $methods) {
            foreach($methods as $httpMethod => $schema) {
                if ( !file_exists($this->currentWorkingDirectory . '/paths/' . $schema['$ref'])) {
                    $this->printPathInfo($path);

                    $file = $this->createPathDefinitionFromTemplate($schema['$ref'], $httpMethod, $path);
                    echo "-> Create endpoint path from template: $file[path]\n";

                    $this->getSchemasFromTemplate($file);
                }
            }
        }
    }

    public function printPathInfo($path)
    {
        echo "--------------------------\n";
        echo "new paths found:\n";
        echo "--------------------------\n";
        echo "$path\n";
    }

    public function getFileNameWithNoExtension(string $filePath)
    {
        $info = $this->getFile($filePath);

        return pathinfo($info->getFilename(), PATHINFO_FILENAME);
    }

    public function getFile(string $filePath)
    {
        return new \SplFileInfo($filePath);
    }

    /**
     * Create path definition files from template
     */
    public function createPathDefinitionFromTemplate(string $filePathReference, string $httpMethod, string $pathTagName = '')
    {
        // get template content
        $name = $this->getFileNameWithNoExtension($filePathReference);
        $template = $this->getContentFromPathTemplate($name, $httpMethod, $pathTagName);

        // create path file from index
        $newPathFile = sprintf(
            $this->currentWorkingDirectory . '/paths/%s%s.yaml',
            $name,
            '' //ucfirst($httpMethod)
        );

        // create from template
        file_put_contents($newPathFile, $template);

        return [
            'path' => $newPathFile,
            'content' => $template,
        ];
    }

    public function getContentFromPathTemplate(string $fileName, string $httpMethod, $pathTagName)
    {
        // check if there is an specific template for method
        $templateFile = $this->getFileTemplate($this->currentWorkingDirectory . '/paths/template.yaml', $httpMethod);

        $content = file_get_contents($templateFile->getPathname());

        $definitionFileName = sprintf(
            '%s%s',
            $fileName,
            ucfirst($httpMethod)
        );

        $content = str_replace(
            [
                '{FILENAME}',
                '{PATH_TAG}'
            ],
            [
                $definitionFileName,
                $this->cleanPathTagName($pathTagName),
            ],
            $content
        );

        return $content;
    }

    public function getFileTemplate(string $filePath, string $httpMethod)
    {
        // get generic file
        $templateName = $this->getFileNameWithNoExtension($filePath);

        // try to get a template for specific method
        $fileMethod = $this->getFile(
            sprintf($this->currentWorkingDirectory . '/paths/%s.%s.yaml', $templateName, $httpMethod)
        );

        return $fileMethod->isFile() ?  $fileMethod : $this->getFile($filePath);
    }

    public function cleanPathTagName(string $pathTagName)
    {
        return substr($pathTagName, 1);
    }

    /**
     * Get all definitions
     */
    public function getSchemasFromTemplate(array $file)
    {
        $template = Yaml::parseFile($file['path']);
        $references = $this->getReferencesFromTemplate($template);

        $mapping = [];

        // process request
        if ($references['requestBody'] ?? false) {
            // go up one level to set the path at root level
            $rootPath = substr($references['requestBody'], 1);
            $file = $this->getFile($rootPath);

            $definitionFile = $this->createEmptySchemaFromTemplate($file);
            $sampleFile = $this->createEmptySampleJsonFile($file, 'requestBody');

            $mapping['requestBody'] = [
                'definitionFile' => $this->currentWorkingDirectory . '/schemas/' . $definitionFile,
                'sampleFile' => $this->currentWorkingDirectory . '/samples/' . $sampleFile,
            ];
        }

        // process responses
        foreach($references['responses'] as $key => $referencePath) {
            // go up one level to set the path at root level
            $rootPath = substr($referencePath, 1);
            $file = $this->getFile($rootPath);

            $definitionFile = $this->createEmptySchemaFromTemplate($file);
            $sampleFile = $this->createEmptySampleJsonFile($file, "Response$key");

            $mapping['responses'][] = [
                'definitionFile' => $this->currentWorkingDirectory . '/responses/' . $definitionFile,
                'sampleFile' => $this->currentWorkingDirectory . '/samples/' . $sampleFile,
            ];
        }

        // add mapping
        $this->addMapping($mapping);
    }

    public function addMapping(array $mapping)
    {
        $file = $this->getFile($this->currentWorkingDirectory . '/config/mapping.json');
        $data = [];

        if ($file->isWritable()) {
            $data = json_decode(file_get_contents($file->getPathName()), true);
        }

        $data['definitions'][] = $mapping;

        file_put_contents($file->getPathName(), json_encode($data, JSON_UNESCAPED_SLASHES));

        printf("-> Add new mapping to %s\n", $file->getPathName());
    }

    public function createEmptySchemaFromTemplate($file)
    {
        // create only if schema don't exists
        if (!$file->isReadable()) {
            touch($file->getPathName());

            echo sprintf(
                "-> Create empty definition file from template: %s \n",
                $file->getPathName()
            );
        }

        return $file->getFileName();
    }

    /**
     * Create empty json request, response, error files.
     */
    public function createEmptySampleJsonFile($file, $type)
    {
        $fileName = sprintf(
            '%s%s.json',
            $this->getFileNameOnlyFromPathInfo($file->getFileName()),
            ucfirst($type)
        );

        $filePath = sprintf(
            '%s%s',
            $this->currentWorkingDirectory . '/samples/',
            $fileName
        );

        // create
        if (!file_exists($filePath)) {
            touch($filePath);
            printf("-> Create empty sample json file: %s\n", $filePath);
        }

        return $fileName;
    }

    public function getFileNameOnlyFromPathInfo(string $filePath)
    {
        return pathinfo($filePath, PATHINFO_FILENAME);
    }

    // create definition files
    public function getReferencesFromTemplate(array $data)
    {
        $files = [];

        $request = $this->getRequestBodyRef($data);

        if (!empty($request)) {
            $files['requestBody'] = $request;
        }

        $responses = $this->getResponsesRef($data);
        $files['responses'] = $responses;

        return $files;
    }

    public function getRequestBodyRef(array $data)
    {
        $referenceValue = null;
        $requestBody = $data['requestBody'] ?? [];
        $this->getReferences($requestBody, $referenceValue);

        return $referenceValue;
    }

    public function getResponsesRef(array $data)
    {
        $files = [];
        $responses = $data['responses'] ?? [];
        $referenceValue = null;

        foreach($responses as $key => $value) {
            $this->getReferences($value, $referenceValue);
            $files[$key] = $referenceValue;
        }

        return $files;
    }

    public function getReferences($data, &$referenceValue)
    {
        foreach ( $data as $key => $value ) {
            if ( $key === '$ref' ) {
                $referenceValue = $value;
            }

            if ( is_array( $value ) ) {
                $this->getReferences($value, $referenceValue);
            }
        }
    }
}