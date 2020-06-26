<?php
namespace Openapi\Generator;

use GAubry\Logger\ColoredIndentedLogger;

/**
 * Class Converter
 * Convert json sample request responses (from API) to open api definition files
 *
 * @package Openapi\Generator
 */
class Converter
{
    private $loggerConfig = [
        'colors' => [
            'debug'      => "\033[0;35m",
            'error'      => "\033[1;31m",
            'section'    => "\033[1;37m",
            'subsection' => "\033[1;33m",
            'ok'         => "\033[1;32m"
        ]
    ];

    /**
     * @var ColoredIndentedLogger
     */
    private $logger;

    private $mappingFilePath;

    private $currentWorkingDirectory;

    public function __construct()
    {
        $this->logger = new ColoredIndentedLogger($this->loggerConfig);
        $this->logger->info('{C.section}Converting Json to AOS+++');
    }

    public function getMappingFile(): array
    {
        $mappingFile = $this->getFile($this->mappingFilePath);
        $this->logger->info("Step 1 - Getting Mapping File+++");

        if ($mappingFile->isReadable()) {
            $mapping = json_decode(file_get_contents($mappingFile->getPathname()), true);
            $this->logger->info(
                '{filePath} is {result}---',
                ['result' => '{C.ok}OK', 'filePath' => $mappingFile->getPathname()]
            );
        } else {
            $this->logger->error(
                'Mapping file {filePath}  was not found!',
                ['filePath' => $mappingFile->getPathname()]
            );
            die(1);
        }

        return $mapping;
    }

    public function convertJsonToOAS(array $mapping): void
    {
        $this->logger->info('Step 2 - Generate OAS Definition from Sample Requests (Json Files)+++');
        foreach($mapping['definitions'] as $definition) {
            $requestBody = $definition['requestBody'] ?? false;
            if ($requestBody) {
                $this->processRequest($requestBody);
            }
        }

        $this->logger->info("---");
        $this->logger->info("\nStep 3 - Generate OAS Definition from Sample Responses (Json Files)+++");
        foreach($mapping['definitions'] as $definition) {
            $this->processResponses($definition['responses']);
        }
    }

    protected function processRequest(array $request)
    {
        // check if file exists (make sure to pass the full path)
        $file = $this->getFile($this->getFullPath($request['sampleFile'] ?? ''));

        if ($file->isFile() && $file->getSize() > 0) {
            // convert json to oas (male sure to pass the full path)
            $json = json_decode(file_get_contents($file->getPathname()), true);

            $this->logger->info(
                "Loading file {filePath} {result}",
                ['result' => '{C.ok}OK', 'filePath' => $file->getPathname()]
            );

            // convert sample to openApi
            $this->logger->info(
                "Converting json to OAS {result}",
                ['result' => '{C.ok}OK']
            );

            $parser = new JsonToOpenApi();
            $parser->loopPropertiesRecursive($json, $parser->openapiData);

            $copy  = $parser->openapiData;
            $parser->fix($copy);

            // $description = $request['description'] ?? false;
            $required = $request['required'] ?? false;

            $finalData = [];

            if ($required) {
                $finalData['required'] = $required;
            }

            $finalData['type'] = 'object';
            $finalData['properties'] = $copy;
            $finalData['example'] = [
                '$ref' => $request['sampleFile']
            ];

            // convert to yaml definition
            $this->logger->info(
                "Converting OAS to YAML {result}",
                ['result' => '{C.ok}OK']
            );
            $yaml = $parser->toYaml($finalData);

            // write definition to file
            $this->logger->info(
                "Saving {pathFile} {result}\n",
                ['result' => '{C.ok}OK', 'pathFile' => $request['definitionFile']]
            );
            file_put_contents($this->getFullPath($request['definitionFile']), $yaml);
        } else {
            $this->printError($file);
        }
    }

    public function printError($file)
    {
        $message = 'The file {filePath} was not found (it will be ignored)!';

        if ($file->isFile() && $file->getSize() === 0) {
            $message = 'The file {filePath} is empty. Please put the sample json data, save and try again';
        }

        $this->logger->error($message, ['filePath' => $file->getPathname()]);
    }

    public function getFullPath($filePath): string
    {
        // check if starts with dot
        $isRelativePath = substr( $filePath, 0, 2 ) === '..';

        if ($isRelativePath) {
            $newPath = $this->currentWorkingDirectory . substr($filePath, 2);
        } else {
            $newPath = $filePath;
        }

        return $newPath;
    }

    public function getFile(string $filePath): \SplFileInfo
    {
        return new \SplFileInfo($filePath);
    }

    /**
     * @param mixed $mappingFilePath
     */
    public function setMappingFilePath($mappingFilePath): void
    {
        $this->mappingFilePath = $mappingFilePath;
    }

    protected function processResponses(array $responses = [])
    {
        foreach($responses as $response) {
            $this->processResponse($response);
        }
    }

    protected function processResponse(array $response)
    {
        // check if file exists
        $file = $this->getFile($this->getFullPath($response['sampleFile'] ?? ''));

        if ($file->isFile() && $file->getSize() > 0) {
            // convert json to oas
            $json = json_decode(file_get_contents($file->getPathname()), true);

            $this->logger->info(
                "Loading file {filePath} {result}",
                ['result' => '{C.ok}OK', 'filePath' => $file->getPathname()]
            );

            // convert sample to openApi
            $this->logger->info(
                "Converting json to OAS {result}",
                ['result' => '{C.ok}OK']
            );
            $parser = new JsonToOpenApi();
            $parser->loopPropertiesRecursive($json, $parser->openapiData);

            $copy = $parser->openapiData;
            $parser->fix($copy);


            $finalData = [];
            $finalData['type'] = 'object';
            $finalData['example'] = [
                '$ref' => $response['sampleFile']
            ];
            $finalData['properties'] = $copy;

            // convert to yaml definition
            $this->logger->info(
                "Converting OAS to YAML {result}",
                ['result' => '{C.ok}OK']
            );
            $yaml = $parser->toYaml($finalData);

            // write to yaml definition
            $this->logger->info(
                "Saving {pathFile} {result}\n",
                ['result' => '{C.ok}OK', 'pathFile' => $response['definitionFile']]
            );
            file_put_contents($this->getFullPath($response['definitionFile']), $yaml);

            $this->bye();
        } else {
            $this->printError($file);
        }
    }

    public function bye(): void
    {
        $this->logger->info('---------Bye!');
    }

    /**
     * @param mixed $currentDirectory
     */
    public function setCurrentWorkingDirectory($currentWorkingDirectory): void
    {
        $this->currentWorkingDirectory = $currentWorkingDirectory;
    }
}