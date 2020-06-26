MAPPING_FILE=./config/mapping.json
PATHS_FILE=./paths/_index.yaml


build_index:
	@swagger-cli bundle openapi.yaml --outfile index.yaml --type yaml
	@swagger-cli validate index.yaml

run_swagger_viewer:
	@swagger-ui-watcher index.yaml

run_swagger_editor:
	@swagger-ui-editor index.yaml


add_mapping:
	# add path
	# add schema

	@echo ${MAPPING_FILE} 