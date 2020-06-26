MAPPING_FILE=./config/mapping.json
PATHS_FILE=./paths/_index.yaml

# dependencies needed by this tool
install_dependencies:
	sudo npm install --global swagger-editor-live swagger-ui-watcher openapi-types @apidevtools/swagger-cli

build_index:
	@swagger-cli bundle openapi.yaml --outfile index.yaml --type yaml
	@swagger-cli validate index.yaml

run_swagger_viewer:
	@swagger-ui-watcher index.yaml --port=8002

run_swagger_editor:
	@swagger-ui-editor index.yaml --port=8001


add_mapping:
	# add path
	# add schema

	@echo ${MAPPING_FILE} 