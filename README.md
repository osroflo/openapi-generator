# openapi-generator
The `openapi-generator` allows to create openapi or swagger definitions by providing sample json requests and reponses.

## Requirements
- PHP 7.3 >
- Composer

## How to Use

### 1. Install
Require `openapi-generator` in your composer.json
```
"repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:osroflo/openapi-generator.git"
        }
 ],
 "require": {
    "osroflo/openapi-generator": "1.0.0"
 }
 ```
Install dependencies
```bash
composer install
```

Test the command
```bash
./vendor/bin/openapi-generator --help   
Generate OpenAPI definitions (swagger) from json request|response v1.0.0

Usage: ./vendor/bin/openapi-generator [--help] [--initialize] [--run-path-indexer] [--run-sample-converter]

Optional Arguments:
        --initialize
                Setup a blank structure to start the documentation project.
        --run-path-indexer
                Generate blank files from path index
        --run-sample-converter
                Convert json sample request responses to definition files.
        --help
                Prints a usage statement
```

### 2. Initialize


### 3. Initialize

### 4. Initialize
