<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

final class NTC_Admin {
	private NTC_Repository $repo;
	private NTC_Migrator $migrator;
	public function __construct( NTC_Repository $repo, NTC_Migrator $migrator ) {
		$this->repo     = $repo;
		$this->migrator = $migrator;}

	public function register_menu(): void {
		add_menu_page( __( 'Data Tables & Charts', 'native-tables-charts' ), __( 'Tables & Charts', 'native-tables-charts' ), 'ntc_edit_datasets', 'ntc-data', array( $this, 'library_page' ), 'dashicons-chart-bar', 58 );
		add_submenu_page( 'ntc-data', __( 'Data Library', 'native-tables-charts' ), __( 'Data Library', 'native-tables-charts' ), 'ntc_edit_datasets', 'ntc-data', array( $this, 'library_page' ) );
		add_submenu_page( 'ntc-data', __( 'Style Presets', 'native-tables-charts' ), __( 'Style Presets', 'native-tables-charts' ), 'ntc_manage_presets', 'ntc-presets', array( $this, 'presets_page' ) );
		add_submenu_page( 'ntc-data', __( 'Import & Export', 'native-tables-charts' ), __( 'Import & Export', 'native-tables-charts' ), 'ntc_export', 'ntc-tools', array( $this, 'tools_page' ) );
		add_submenu_page( 'ntc-data', __( 'League Table Migration', 'native-tables-charts' ), __( 'Migration', 'native-tables-charts' ), 'ntc_migrate', 'ntc-migration', array( $this, 'migration_page' ) );
		add_submenu_page( 'ntc-data', __( 'Settings', 'native-tables-charts' ), __( 'Settings', 'native-tables-charts' ), 'ntc_manage_settings', 'ntc-settings', array( $this, 'settings_page' ) );
	}

	public function admin_assets( string $hook ): void {
		if ( ! str_contains( $hook, 'ntc-' ) ) {
			return;
		}
		$version = NTC_VERSION . '.' . filemtime( NTC_DIR . 'assets/css/admin.css' );
		wp_enqueue_style( 'ntc-admin', NTC_URL . 'assets/css/admin.css', array(), $version );
	}

	public function migration_detect_ajax(): void {
		check_ajax_referer( 'ntc_migration_detect', 'nonce' );
		if ( ! current_user_can( 'ntc_migrate' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'native-tables-charts' ) ), 403 ); }
		$uid   = get_current_user_id();
		$key   = 'ntc_migration_detection_' . $uid;
		$state = get_transient( $key );
		$state = $this->migrator->detect_batch( is_array( $state ) ? $state : array(), 100 );
		set_transient( $key, $state, HOUR_IN_SECONDS );
		if ( ! empty( $state['complete'] ) ) {
			set_transient( 'ntc_migration_targets_pending_' . $uid, array( 'post_ids' => (array) $state['post_ids'], 'instances' => (int) $state['instances'] ), HOUR_IN_SECONDS ); }
		$safe = $state;
		unset( $safe['post_ids'], $safe['schema'] );
		wp_send_json_success( $safe );
	}

	public function handle_actions(): void {
		if ( ! is_admin() || ! isset( $_POST['ntc_action'] ) ) {
			return;
		}
		check_admin_referer( 'ntc_admin_action' );
		$action = sanitize_key( wp_unslash( $_POST['ntc_action'] ) );
		if ( 'delete_dataset' === $action && current_user_can( 'ntc_delete_datasets' ) ) {
			$this->repo->delete_dataset( absint( $_POST['dataset_id'] ?? 0 ) );
			$this->redirect( 'ntc-data', 'deleted=1' );}
		if ( 'duplicate_dataset' === $action && current_user_can( 'ntc_create_datasets' ) ) {
			$source_id = absint( $_POST['dataset_id'] ?? 0 );
			$source    = $this->repo->get_dataset( $source_id, true );
			if ( $source ) {
				/* translators: %s: dataset name. */
				$new_id = $this->repo->create_dataset( sprintf( __( '%s Copy', 'native-tables-charts' ), $source['name'] ), $source['columns'], $source['rows'] ?? array(), $source['description'] ?? '' );
				if ( $new_id ) {
					foreach ( $this->repo->list_views( $source_id ) as $view ) {
								/* translators: %s: view name. */
								$this->repo->create_view( $new_id, $view['type'], sprintf( __( '%s Copy', 'native-tables-charts' ), $view['name'] ), $view['config'] );
					}
				}
			}$this->redirect( 'ntc-data', 'duplicated=1' );}
		if ( 'delete_preset' === $action && current_user_can( 'ntc_manage_presets' ) ) {
			$this->repo->delete_preset( absint( $_POST['preset_id'] ?? 0 ) );
			$this->redirect( 'ntc-presets', 'deleted=1' );}
		if ( 'export_preset' === $action && current_user_can( 'ntc_manage_presets' ) ) {
			$preset = $this->repo->get_preset( absint( $_POST['preset_id'] ?? 0 ) );
			if ( ! $preset ) {
				wp_die( esc_html__( 'Preset not found.', 'native-tables-charts' ) );
			}$payload = array(
				'nativeTablesChartsPreset' => 1,
				'name'                     => $preset['name'],
				'type'                     => $preset['type'],
				'settings'                 => $preset['settings'],
			);
			nocache_headers();
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( ! empty( $preset['slug'] ) ? $preset['slug'] : 'ntc-preset' ) . '.json"' );
			echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
			exit;}
		if ( 'import_preset' === $action && current_user_can( 'ntc_manage_presets' ) ) {
			$raw = '';
			if ( ! empty( $_FILES['preset_file']['tmp_name'] ) && is_uploaded_file( $_FILES['preset_file']['tmp_name'] ) && (int) ( $_FILES['preset_file']['size'] ?? 0 ) <= 2 * MB_IN_BYTES ) {
				$raw = (string) file_get_contents( $_FILES['preset_file']['tmp_name'] );
			} elseif ( isset( $_POST['preset_json'] ) ) {
				$raw = (string) wp_unslash( $_POST['preset_json'] );
			}$data = json_decode( $raw, true );
			if ( is_array( $data ) && in_array( $data['type'] ?? '', array( 'table', 'chart' ), true ) && is_array( $data['settings'] ?? null ) ) {
				$this->repo->create_preset( $data['type'], sanitize_text_field( $data['name'] ?? __( 'Imported preset', 'native-tables-charts' ) ), $data['settings'] );
				$this->redirect( 'ntc-presets', 'imported=1' );
			}$this->redirect( 'ntc-presets', 'import_error=1' );}
		if ( 'export_dataset_bundle' === $action && current_user_can( 'ntc_export' ) ) {
			$id      = absint( $_POST['dataset_id'] ?? 0 );
			$payload = $this->build_backup_payload( $id ? array( $id ) : array() );
			if ( empty( $payload['datasets'] ) ) {
				wp_die( esc_html__( 'Dataset not found.', 'native-tables-charts' ) );
			}$this->send_json_download( $payload, 'native-tables-charts-dataset-' . $id . '.json' );}
		if ( 'export_all_bundle' === $action && current_user_can( 'ntc_export' ) ) {
			$this->send_json_download( $this->build_backup_payload(), 'native-tables-charts-backup-' . gmdate( 'Y-m-d' ) . '.json' );}
		if ( 'import_native_bundle' === $action && current_user_can( 'ntc_import' ) ) {
			$result = array(
				'success' => false,
				'error'   => __( 'No Native Tables & Charts JSON file was uploaded.', 'native-tables-charts' ),
			);
			$size   = (int) ( $_FILES['native_bundle']['size'] ?? 0 );
			if ( ! empty( $_FILES['native_bundle']['tmp_name'] ) && is_uploaded_file( $_FILES['native_bundle']['tmp_name'] ) && $size <= 50 * MB_IN_BYTES ) {
				$raw     = (string) file_get_contents( $_FILES['native_bundle']['tmp_name'] );
				$payload = json_decode( $raw, true );
				$result  = $this->import_backup_payload( is_array( $payload ) ? $payload : array() );
			} elseif ( $size > 50 * MB_IN_BYTES ) {
				$result = array(
					'success' => false,
					'error'   => __( 'The Native Tables & Charts backup is larger than the 50 MB import limit.', 'native-tables-charts' ),
				);
			}set_transient( 'ntc_tools_result_' . get_current_user_id(), $result, 30 * MINUTE_IN_SECONDS );
			$this->redirect( 'ntc-tools', 'imported=1' );}
		if ( 'migration_reset' === $action && current_user_can( 'ntc_migrate' ) ) {
			$uid      = get_current_user_id();
			$progress = get_transient( 'ntc_migration_progress_' . $uid );
			$batch_id = sanitize_text_field( wp_unslash( $_POST['batch_id'] ?? ( is_array( $progress ) ? ( $progress['batch_id'] ?? '' ) : '' ) ) );
			$this->migrator->clear_migration_state( $batch_id );
			delete_transient( 'ntc_migration_progress_' . $uid );
			delete_transient( 'ntc_migration_targets_pending_' . $uid );
			delete_transient( 'ntc_migration_detection_' . $uid );
			$this->redirect( 'ntc-migration', 'migration_reset=1' );
		}
		if ( 'migration_dry_run' === $action && current_user_can( 'ntc_migrate' ) ) {
			$uid = get_current_user_id();
			$detected = get_transient( 'ntc_migration_detection_' . $uid );
			if ( ! is_array( $detected ) || empty( $detected['complete'] ) ) {
				$this->redirect( 'ntc-migration', 'detection_required=1' ); }
			set_transient( 'ntc_migration_report_' . $uid, $this->migrator->dry_run( $detected ), 10 * MINUTE_IN_SECONDS );
			$this->redirect( 'ntc-migration', 'dryrun=1' );}
		if ( 'migration_run' === $action && current_user_can( 'ntc_migrate' ) ) {
			$batch_id    = sanitize_text_field( wp_unslash( $_POST['batch_id'] ?? '' ) );
			$uid         = get_current_user_id();
			$prev        = '' !== $batch_id ? get_transient( 'ntc_migration_progress_' . $uid ) : false;
			if ( is_array( $prev ) && NTC_Migrator::MIGRATION_STATE_VERSION !== (int) ( $prev['state_version'] ?? 0 ) ) {
				$this->migrator->clear_migration_state( $batch_id );
				delete_transient( 'ntc_migration_progress_' . $uid );
				delete_transient( 'ntc_migration_targets_pending_' . $uid );
				$this->redirect( 'ntc-migration', 'migration_reset=1' );
			}
			if ( is_array( $prev ) && ! empty( $prev['errors'] ) ) {
				$prev['errors'] = array();
			}
			$convert     = is_array( $prev ) ? ! empty( $prev['convert'] ) : ! empty( $_POST['convert_content'] );
			$offset      = absint( $_POST['offset'] ?? ( is_array( $prev ) ? ( $prev['offset'] ?? 0 ) : 0 ) );
			$cursor      = absint( $_POST['cursor'] ?? ( is_array( $prev ) ? ( $prev['cursor'] ?? 0 ) : 0 ) );
			$table_cursor = absint( $_POST['table_cursor'] ?? ( is_array( $prev ) ? ( $prev['table_cursor'] ?? 0 ) : 0 ) );
			$target_offset = absint( $_POST['target_offset'] ?? ( is_array( $prev ) ? ( $prev['target_offset'] ?? 0 ) : 0 ) );
			$chunk       = NTC_Migrator::POST_BATCH_SIZE;
			if ( '' !== $batch_id ) {
				$result = $this->migrator->continue_migration( $batch_id, $cursor, $chunk, $table_cursor, $convert, $target_offset );
			} else {
				$pending_targets = get_transient( 'ntc_migration_targets_pending_' . $uid );
				if ( $convert && ! is_array( $pending_targets ) ) {
					delete_transient( 'ntc_migration_detection_' . $uid );
					$this->redirect( 'ntc-migration', 'detection_required=1' ); }
				$target_ids      = is_array( $pending_targets ) ? (array) ( $pending_targets['post_ids'] ?? array() ) : array();
				$target_instances = is_array( $pending_targets ) ? (int) ( $pending_targets['instances'] ?? 0 ) : 0;
				$result          = $this->migrator->migrate( $convert, 0, $chunk, $target_ids, $target_instances );
				delete_transient( 'ntc_migration_targets_pending_' . $uid );
			}
			$batch_id         = (string) ( $result['batch_id'] ?? $batch_id );
			$total            = is_array( $prev ) && ! empty( $prev['total'] ) ? (int) $prev['total'] : (int) ( $result['posts_total'] ?? 0 );
			$new_offset       = ! empty( $result['targets_refreshed'] ) ? (int) ( $result['processed'] ?? 0 ) : $offset + (int) ( $result['processed'] ?? 0 );
			$next_cursor      = (int) ( $result['next_cursor'] ?? $cursor );
			$next_target_offset = (int) ( $result['target_offset'] ?? $target_offset );
			$table_total      = is_array( $prev ) && isset( $prev['table_total'] ) ? (int) $prev['table_total'] : (int) ( $result['tables_total'] ?? 0 );
			$table_offset     = ( is_array( $prev ) ? (int) ( $prev['table_offset'] ?? 0 ) : 0 ) + (int) ( $result['tables_processed'] ?? 0 );
			$next_table_cursor = (int) ( $result['next_table_cursor'] ?? $table_cursor );
			$done             = empty( $batch_id ) || ! empty( $result['migration_complete'] );
			$acc_errors       = array_merge( is_array( $prev ) ? (array) ( $prev['errors'] ?? array() ) : array(), (array) ( $result['errors'] ?? array() ) );
			$acc_datasets     = ( is_array( $prev ) ? (int) ( $prev['datasets'] ?? 0 ) : 0 ) + (int) ( $result['datasets_created'] ?? 0 );
			$acc_posts        = ( is_array( $prev ) ? (int) ( $prev['posts'] ?? 0 ) : 0 ) + (int) ( $result['posts_updated'] ?? 0 );
			$acc_instances    = ( is_array( $prev ) ? (int) ( $prev['instances'] ?? 0 ) : 0 ) + (int) ( $result['instances_converted'] ?? 0 );
			$instance_total   = is_array( $prev ) && ! empty( $prev['instance_total'] ) ? (int) $prev['instance_total'] : (int) ( $result['instances_total'] ?? ( is_array( $pending_targets ?? null ) ? (int) ( $pending_targets['instances'] ?? 0 ) : 0 ) );
			if ( $done ) {
				$result['success']             = empty( $acc_errors );
				$result['errors']              = $acc_errors;
				$result['datasets_created']    = $acc_datasets;
				$result['posts_updated']       = $acc_posts;
				$result['instances_converted'] = $acc_instances;
				delete_transient( 'ntc_migration_progress_' . $uid );
				set_transient( 'ntc_migration_result_' . $uid, $result, 30 * MINUTE_IN_SECONDS );
				$this->redirect( 'ntc-migration', 'migrated=1' );
			}
			set_transient(
				'ntc_migration_progress_' . $uid,
				array(
					'state_version' => NTC_Migrator::MIGRATION_STATE_VERSION,
					'done'      => false,
					'convert'   => $convert,
					'batch_id'  => $batch_id,
					'offset'    => $new_offset,
					'cursor'    => $next_cursor,
					'target_offset' => $next_target_offset,
					'table_offset' => $table_offset,
					'table_cursor' => $next_table_cursor,
					'table_total' => $table_total,
					'table_stage' => (string) ( $result['table_stage'] ?? '' ),
					'current_table' => (int) ( $result['current_table'] ?? 0 ),
					'total'     => $total,
					'instance_total' => $instance_total,
					'phase'     => (string) ( $result['phase'] ?? 'tables' ),
					'errors'    => $acc_errors,
					'datasets'  => $acc_datasets,
					'posts'     => $acc_posts,
					'instances' => $acc_instances,
				),
				HOUR_IN_SECONDS
			);
			$this->redirect( 'ntc-migration', 'migrated=1' );
		}
		if ( 'migration_xml_import' === $action && ( current_user_can( 'ntc_migrate' ) || current_user_can( 'ntc_import' ) ) ) {
			$result = array(
				'success' => false,
				'error'   => __( 'No XML file was uploaded.', 'native-tables-charts' ),
			);
			if ( ! empty( $_FILES['league_xml']['tmp_name'] ) && is_uploaded_file( $_FILES['league_xml']['tmp_name'] ) && (int) ( $_FILES['league_xml']['size'] ?? 0 ) <= 20 * MB_IN_BYTES ) {
				$xml = file_get_contents( $_FILES['league_xml']['tmp_name'] );
				if ( false !== $xml ) {
					$result = $this->migrator->import_xml( $xml );
				}
			} elseif ( ! empty( $_FILES['league_xml']['size'] ) && (int) $_FILES['league_xml']['size'] > 20 * MB_IN_BYTES ) {
				$result = array(
					'success' => false,
					'error'   => __( 'The League Table XML file is larger than the 20 MB import limit.', 'native-tables-charts' ),
				);
			}set_transient( 'ntc_migration_result_' . get_current_user_id(), $result, 30 * MINUTE_IN_SECONDS );
			$this->redirect( 'ntc-migration', 'xmlimport=1' );}
		if ( 'migration_rollback' === $action && current_user_can( 'ntc_migrate' ) ) {
			$batch        = sanitize_text_field( wp_unslash( $_POST['batch_id'] ?? '' ) );
			$offset       = absint( $_POST['offset'] ?? 0 );
			$result       = $this->migrator->rollback( $batch, $offset, 200 );
			$uid          = get_current_user_id();
			$prev         = get_transient( 'ntc_rollback_progress_' . $uid );
			$acc_errors   = array_merge( is_array( $prev ) ? (array) ( $prev['errors'] ?? array() ) : array(), (array) ( $result['errors'] ?? array() ) );
			$acc_restored = ( is_array( $prev ) ? (int) ( $prev['restored'] ?? 0 ) : 0 ) + (int) ( $result['restored'] ?? 0 );
			$new_offset   = $offset + (int) ( $result['processed'] ?? 0 );
			$done         = 0 === (int) ( $result['remaining'] ?? 0 );
			if ( $done ) {
				$result['errors']   = $acc_errors;
				$result['restored'] = $acc_restored;
				delete_transient( 'ntc_rollback_progress_' . $uid );
				set_transient( 'ntc_migration_result_' . $uid, $result, 30 * MINUTE_IN_SECONDS );
				$this->redirect( 'ntc-migration', 'rolledback=1' );
			}
			set_transient(
				'ntc_rollback_progress_' . $uid,
				array(
					'done'     => false,
					'batch_id' => $batch,
					'offset'   => $new_offset,
					'total'    => (int) ( $result['total'] ?? 0 ),
					'errors'   => $acc_errors,
					'restored' => $acc_restored,
				),
				HOUR_IN_SECONDS
			);
			$this->redirect( 'ntc-migration', 'rolledback=1' );
		}
		if ( 'save_settings' === $action && current_user_can( 'ntc_manage_settings' ) ) {
			update_option( 'ntc_delete_data_on_uninstall', ! empty( $_POST['delete_data'] ) ? 1 : 0 );
			$features = array();
			foreach ( array_keys( NTC_Renderer::cell_feature_defaults() ) as $key ) {
				$features[ $key ] = ! empty( $_POST['cell_features'][ $key ] );
			}update_option( 'ntc_cell_features', $features, false );
			update_option( 'ntc_kses_allowed_html_tags', sanitize_text_field( wp_unslash( $_POST['kses_allowed_html_tags'] ?? '' ) ), false );
			update_option( 'ntc_kses_allowed_protocols', sanitize_text_field( wp_unslash( $_POST['kses_allowed_protocols'] ?? '' ) ), false );
			$this->redirect( 'ntc-settings', 'saved=1' );}
		if ( 'update_source' === $action && current_user_can( 'manage_options' ) ) {
			$this->repo->set_source( absint( $_POST['dataset_id'] ?? 0 ), (string) wp_unslash( $_POST['source_url'] ?? '' ) );
			$this->redirect( 'ntc-data', 'source=1' );
		}
		if ( 'refresh_source' === $action && current_user_can( 'manage_options' ) ) {
			$id     = absint( $_POST['dataset_id'] ?? 0 );
			$result = NTC_Sync::sync_dataset( $this->repo, $id );
			set_transient( 'ntc_tools_result_' . get_current_user_id(), $result, 30 * MINUTE_IN_SECONDS );
			$this->redirect( 'ntc-data', 'source=1' );
		}
		if ( 'update_post_source' === $action && current_user_can( 'ntc_import' ) ) {
			$mode   = 'posts' === sanitize_key( wp_unslash( $_POST['source_mode'] ?? '' ) ) ? 'posts' : '';
			$config = array(
				'post_type'  => sanitize_key( wp_unslash( $_POST['post_type'] ?? 'post' ) ),
				'meta_label' => sanitize_text_field( wp_unslash( $_POST['meta_label'] ?? '' ) ),
				'meta_value' => array_values( array_filter( array_map( 'trim', explode( ',', (string) wp_unslash( $_POST['meta_value'] ?? '' ) ) ) ) ),
			);
			$this->repo->set_post_source( absint( $_POST['dataset_id'] ?? 0 ), $mode, $config );
			$this->redirect( 'ntc-data', 'source=1' );
		}
	}

	private function redirect( string $page, string $query = '' ): void {
		wp_safe_redirect( admin_url( 'admin.php?page=' . $page . ( $query ? '&' . $query : '' ) ) );
		exit;}

	private function send_json_download( array $payload, string $filename ): void {
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	private function build_backup_payload( array $only_ids = array() ): array {
		$datasets = array();
		$ids      = array_map( 'absint', $only_ids );
		if ( ! $ids ) {
			$offset = 0;
			do {
				$page = $this->repo->list_datasets( 500, $offset );
				foreach ( $page as $item ) {
					$ids[] = (int) $item['id'];
				}$offset += count( $page );
			} while ( count( $page ) === 500 );}
		foreach ( array_values( array_unique( array_filter( $ids ) ) ) as $id ) {
			$dataset = $this->repo->get_dataset( $id, true );
			if ( ! $dataset ) {
				continue;
			}$views = array();
			foreach ( $this->repo->list_views( $id ) as $view ) {
				$views[] = array(
					'name'   => $view['name'],
					'type'   => $view['type'],
					'config' => $view['config'],
				);
			}$datasets[] = array(
				'name'        => $dataset['name'],
				'description' => $dataset['description'] ?? '',
				'columns'     => $dataset['columns'] ?? array(),
				'rows'        => $dataset['rows'] ?? array(),
				'views'       => $views,
			);}
		$presets = array();
		if ( ! $only_ids && current_user_can( 'ntc_manage_presets' ) ) {
			foreach ( $this->repo->list_presets() as $preset ) {
				$presets[] = array(
					'name'     => $preset['name'],
					'type'     => $preset['type'],
					'settings' => $preset['settings'],
				);}
		}
		return array(
			'nativeTablesChartsBackup' => 1,
			'pluginVersion'            => NTC_VERSION,
			'exportedAt'               => gmdate( 'c' ),
			'scope'                    => $only_ids ? 'dataset' : 'complete',
			'datasets'                 => $datasets,
			'presets'                  => $presets,
		);
	}

	private function import_backup_payload( array $payload ): array {
		// Accept full NTC backups, single-dataset bundles, and raw NTC data JSON exports.
		if ( isset( $payload['columns'], $payload['rows'] ) && ! isset( $payload['datasets'] ) ) {
			$payload = array(
				'datasets' => array(
					array(
						'name'        => $payload['name'] ?? __( 'Imported Dataset', 'native-tables-charts' ),
						'description' => $payload['description'] ?? '',
						'columns'     => $payload['columns'],
						'rows'        => $payload['rows'],
						'views'       => $payload['views'] ?? array(),
					),
				),
				'presets'  => array(),
			);}
		$datasets = (array) ( $payload['datasets'] ?? array() );
		if ( ! $datasets ) {
			return array(
				'success' => false,
				'error'   => __( 'The file does not contain any usable Native Tables & Charts datasets.', 'native-tables-charts' ),
			);
		}
		$created         = 0;
		$views_created   = 0;
		$presets_created = 0;
		$errors          = array();
		foreach ( array_slice( $datasets, 0, 500 ) as $index => $dataset ) {
			if ( ! is_array( $dataset ) || empty( $dataset['columns'] ) || ! is_array( $dataset['columns'] ) || ! is_array( $dataset['rows'] ?? null ) ) {
				/* translators: %d: dataset number. */
				$errors[] = sprintf( __( 'Dataset %d is missing columns or rows.', 'native-tables-charts' ), $index + 1 );
				continue;
			}$id = $this->repo->create_dataset( (string) ( $dataset['name'] ?? __( 'Imported Dataset', 'native-tables-charts' ) ), (array) $dataset['columns'], (array) $dataset['rows'], (string) ( $dataset['description'] ?? '' ) );
			if ( ! $id ) {
				/* translators: %d: dataset number. */
				$errors[] = sprintf( __( 'Dataset %d could not be created.', 'native-tables-charts' ), $index + 1 );
				continue;
			}++$created;
			foreach ( array_slice( (array) ( $dataset['views'] ?? array() ), 0, 250 ) as $view ) {
				if ( ! is_array( $view ) ) {
						continue;
				}$type = in_array( $view['type'] ?? '', array( 'table', 'chart' ), true ) ? $view['type'] : 'table';
				if ( $this->repo->create_view( $id, $type, (string) ( $view['name'] ?? ucfirst( $type ) ), (array) ( $view['config'] ?? array() ) ) ) {
					++$views_created;
				}
			}
		}
		if ( current_user_can( 'ntc_manage_presets' ) ) {
			foreach ( array_slice( (array) ( $payload['presets'] ?? array() ), 0, 500 ) as $preset ) {
				if ( ! is_array( $preset ) || ! in_array( $preset['type'] ?? '', array( 'table', 'chart' ), true ) || ! is_array( $preset['settings'] ?? null ) ) {
					continue;
				}if ( $this->repo->create_preset( $preset['type'], (string) ( $preset['name'] ?? __( 'Imported preset', 'native-tables-charts' ) ), $preset['settings'] ) ) {
					++$presets_created;
				}
			}
		}
		return array(
			'success'          => empty( $errors ),
			'datasets_created' => $created,
			'views_created'    => $views_created,
			'presets_created'  => $presets_created,
			'errors'           => $errors,
		);
	}

	private function dataset_post_usage(): array {
		$key    = 'ntc_dataset_usage_counts';
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT ID,post_title,post_content FROM {$wpdb->posts} WHERE post_status NOT IN ('trash','auto-draft','inherit') AND post_content LIKE '%wp:ntc/%'", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! $rows ) {
			$rows = array();
		}
		$usage = array();
		foreach ( $rows as $row ) {
			preg_match_all( '/"datasetId"\s*:\s*(\d+)/', $row['post_content'], $m );
			$ids = array_unique( array_map( 'intval', $m[1] ?? array() ) );
			foreach ( $ids as $id ) {
				if ( ! $id ) {
						continue;
				}if ( ! isset( $usage[ $id ] ) ) {
					$usage[ $id ] = array(
						'count' => 0,
						'posts' => array(),
					);
				}++$usage[ $id ]['count'];
				if ( count( $usage[ $id ]['posts'] ) < 5 ) {
					$usage[ $id ]['posts'][] = array(
						'id'    => (int) $row['ID'],
						/* translators: %d: post ID. */
						'title' => ! empty( $row['post_title'] ) ? $row['post_title'] : sprintf( __( 'Post #%d', 'native-tables-charts' ), $row['ID'] ),
					);
				}
			}
		}
		set_transient( $key, $usage, 5 * MINUTE_IN_SECONDS );
		return $usage;
	}

	public function library_page(): void {
		$search      = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
		$datasets    = $this->repo->list_datasets( 250, 0, $search );
		$views       = $this->repo->list_views();
		$usage       = $this->dataset_post_usage();
		$view_counts = array();
		foreach ( $views as $v ) {
			$view_counts[ (int) $v['dataset_id'] ][] = $v;
		}
		?>
		<div class="wrap ntc-admin"><h1><?php esc_html_e( 'Data Library', 'native-tables-charts' ); ?></h1><p><?php esc_html_e( 'Reusable datasets keep tables and charts in sync across posts. Add a Table or Chart block in Gutenberg, then choose “Save as reusable dataset” from its More menu.', 'native-tables-charts' ); ?></p>
		<?php
		if ( isset( $_GET['source'] ) ) :
			$source_result = get_transient( 'ntc_tools_result_' . get_current_user_id() );if ( is_array( $source_result ) ) :
				?>
			<div class="notice notice-info inline"><p><strong><?php esc_html_e( 'Source update completed', 'native-tables-charts' ); ?></strong></p></div>
			<details class="ntc-technical-details"><summary><?php esc_html_e( 'Technical details', 'native-tables-charts' ); ?></summary><pre class="ntc-result"><?php echo esc_html( wp_json_encode( $source_result, JSON_PRETTY_PRINT ) ); ?></pre></details>
				<?php
		endif;
endif;
		if ( $datasets || '' !== $search ) :
			?>
		<form method="get" class="ntc-search" role="search"><input type="hidden" name="page" value="ntc-data"><label class="screen-reader-text" for="ntc-dataset-search"><?php esc_html_e( 'Search datasets', 'native-tables-charts' ); ?></label><input id="ntc-dataset-search" type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search by dataset name…', 'native-tables-charts' ); ?>"><button class="button"><?php esc_html_e( 'Search', 'native-tables-charts' ); ?></button><?php if ( '' !== $search ) : ?><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=ntc-data' ) ); ?>"><?php esc_html_e( 'Clear', 'native-tables-charts' ); ?></a><?php endif; ?></form>
		<?php endif; ?>
		<table class="widefat striped ntc-list ntc-dataset-list"><thead><tr><th><?php esc_html_e( 'Dataset', 'native-tables-charts' ); ?></th><th><?php esc_html_e( 'Rows', 'native-tables-charts' ); ?></th><th><?php esc_html_e( 'Views', 'native-tables-charts' ); ?></th><th><?php esc_html_e( 'Posts', 'native-tables-charts' ); ?></th><th><?php esc_html_e( 'Updated', 'native-tables-charts' ); ?></th><th><?php esc_html_e( 'Actions', 'native-tables-charts' ); ?></th></tr></thead><tbody>
		<?php
		if ( ! $datasets ) :
			?>
			<tr class="ntc-empty-row"><td colspan="6"><div class="ntc-empty-state"><h2><?php echo esc_html( '' !== $search ? __( 'No datasets match your search', 'native-tables-charts' ) : __( 'No reusable datasets yet', 'native-tables-charts' ) ); ?></h2><p><?php echo esc_html( '' !== $search ? __( 'Try a different name or clear the search to see every dataset.', 'native-tables-charts' ) : __( 'Create a table or chart in a post, then save its data as reusable from the block’s More menu.', 'native-tables-charts' ) ); ?></p><?php if ( '' !== $search ) : ?><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=ntc-data' ) ); ?>"><?php esc_html_e( 'Clear search', 'native-tables-charts' ); ?></a><?php else : ?><a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php' ) ); ?>"><?php esc_html_e( 'Add a table or chart in a post', 'native-tables-charts' ); ?></a><?php endif; ?></div></td></tr><?php endif; ?>
		<?php
		foreach ( $datasets as $d ) :
			$id = (int) $d['id'];
			?>
			<tr><td data-label="<?php esc_attr_e( 'Dataset', 'native-tables-charts' ); ?>"><strong class="ntc-dataset-name"><?php echo esc_html( $d['name'] ); ?></strong>
			<?php
			if ( $d['description'] ) :
				?>
			<div class="description"><?php echo esc_html( $d['description'] ); ?></div><?php endif; ?>
			<?php
			if ( ! empty( $d['source_url'] ?? '' ) ) :
				?>
	<div class="description"><?php esc_html_e( 'Source:', 'native-tables-charts' ); ?> <?php echo esc_html( $d['source_url'] ); ?></div><?php endif; ?>
			<?php
			if ( ! empty( $d['source_error'] ?? '' ) ) :
				?>
	<div class="description" style="color:#d63638"><?php echo esc_html( $d['source_error'] ); ?></div><?php endif; ?>
			<?php
			if ( ! empty( $d['source_last_sync'] ?? '' ) ) :
				?>
	<div class="description"><?php esc_html_e( 'Synced:', 'native-tables-charts' ); ?> <?php echo esc_html( get_date_from_gmt( $d['source_last_sync'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></div><?php endif; ?>
			<?php
			if ( 'posts' === ( $d['source_mode'] ?? '' ) ) :
				?>
	<span class="ntc-source-badge"><?php esc_html_e( 'Posts', 'native-tables-charts' ); ?></span><?php endif; ?></td><td data-label="<?php esc_attr_e( 'Rows', 'native-tables-charts' ); ?>"><?php echo esc_html( $d['row_count'] ); ?></td><td data-label="<?php esc_attr_e( 'Views', 'native-tables-charts' ); ?>"><?php echo esc_html( $d['view_count'] ); ?>
			<?php
			if ( ! empty( $view_counts[ $id ] ) ) :
				?>
	<div class="ntc-view-list">
				<?php
				foreach ( $view_counts[ $id ] as $v ) :
					?>
	<span><?php echo esc_html( ucfirst( $v['type'] ) . ': ' . $v['name'] ); ?></span><?php endforeach; ?></div><?php endif; ?></td><td data-label="<?php esc_attr_e( 'Posts', 'native-tables-charts' ); ?>">
			<?php
			$u = $usage[ $id ] ?? array(
				'count' => 0,
				'posts' => array(),
			);
			echo esc_html( $u['count'] ); if ( ! empty( $u['posts'] ) ) :
				?>
		<div class="ntc-view-list">
				<?php
				foreach ( $u['posts'] as $post ) :
					?>
	<a href="<?php echo esc_url( get_edit_post_link( $post['id'] ) ); ?>"><?php echo esc_html( $post['title'] ); ?></a><?php endforeach; ?></div><?php endif; ?></td><td data-label="<?php esc_attr_e( 'Updated', 'native-tables-charts' ); ?>"><?php echo esc_html( get_date_from_gmt( $d['updated_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></td><td data-label="<?php esc_attr_e( 'Actions', 'native-tables-charts' ); ?>"><div class="ntc-row-actions"><a class="button" href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'format'   => 'json',
						'_wpnonce' => wp_create_nonce( 'wp_rest' ),
					),
					rest_url( 'ntc/v1/export/' . $id )
				)
			);
			?>
"><?php esc_html_e( 'Export data', 'native-tables-charts' ); ?></a><form method="post" class="ntc-inline-form"><?php wp_nonce_field( 'ntc_admin_action' ); ?><input type="hidden" name="ntc_action" value="duplicate_dataset"><input type="hidden" name="dataset_id" value="<?php echo esc_attr( $id ); ?>"><button class="button"><?php esc_html_e( 'Duplicate', 'native-tables-charts' ); ?></button></form></div>
			<details class="ntc-row-more"><summary class="button"><?php esc_html_e( 'More actions', 'native-tables-charts' ); ?></summary><div class="ntc-row-more-content">
			<section class="ntc-action-group"><h3><?php esc_html_e( 'Portable backup', 'native-tables-charts' ); ?></h3><p><?php esc_html_e( 'Includes this dataset, its synced views, and configuration.', 'native-tables-charts' ); ?></p><form method="post"><?php wp_nonce_field( 'ntc_admin_action' ); ?><input type="hidden" name="ntc_action" value="export_dataset_bundle"><input type="hidden" name="dataset_id" value="<?php echo esc_attr( $id ); ?>"><button class="button"><?php esc_html_e( 'Download full backup', 'native-tables-charts' ); ?></button></form></section>
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
			<section class="ntc-action-group"><h3><?php esc_html_e( 'Remote CSV or TSV source', 'native-tables-charts' ); ?></h3><p><?php esc_html_e( 'Use a publicly reachable CSV or TSV URL to refresh this dataset.', 'native-tables-charts' ); ?></p><form method="post" class="ntc-source-form"><?php wp_nonce_field( 'ntc_admin_action' ); ?><input type="hidden" name="ntc_action" value="update_source"><input type="hidden" name="dataset_id" value="<?php echo esc_attr( $id ); ?>"><label for="ntc-source-url-<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Source URL', 'native-tables-charts' ); ?></label><input id="ntc-source-url-<?php echo esc_attr( $id ); ?>" type="url" name="source_url" value="<?php echo esc_attr( $d['source_url'] ?? '' ); ?>" placeholder="https://example.com/data.csv" class="regular-text"><button class="button"><?php esc_html_e( 'Save source URL', 'native-tables-charts' ); ?></button></form><form method="post" class="ntc-inline-form"><?php wp_nonce_field( 'ntc_admin_action' ); ?><input type="hidden" name="ntc_action" value="refresh_source"><input type="hidden" name="dataset_id" value="<?php echo esc_attr( $id ); ?>"><button class="button" <?php disabled( empty( $d['source_url'] ?? '' ) ); ?>><?php esc_html_e( 'Refresh data now', 'native-tables-charts' ); ?></button></form></section><?php endif; ?>
			<?php if ( current_user_can( 'ntc_import' ) ) : $post_cfg = json_decode( (string) ( $d['source_config'] ?? '' ), true ); $post_cfg = is_array( $post_cfg ) ? $post_cfg : array(); ?>
			<section class="ntc-action-group"><h3><?php esc_html_e( 'WordPress posts source', 'native-tables-charts' ); ?></h3><p><?php esc_html_e( 'Build rows from post metadata. Enter meta keys exactly as they appear in WordPress.', 'native-tables-charts' ); ?></p><form method="post" class="ntc-source-form"><?php wp_nonce_field( 'ntc_admin_action' ); ?><input type="hidden" name="ntc_action" value="update_post_source"><input type="hidden" name="dataset_id" value="<?php echo esc_attr( $id ); ?>"><label for="ntc-source-mode-<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Source type', 'native-tables-charts' ); ?></label><select id="ntc-source-mode-<?php echo esc_attr( $id ); ?>" name="source_mode"><option value="" <?php selected( '' === ( $d['source_mode'] ?? '' ) ); ?>><?php esc_html_e( 'None', 'native-tables-charts' ); ?></option><option value="posts" <?php selected( 'posts' === ( $d['source_mode'] ?? '' ) ); ?>><?php esc_html_e( 'WordPress posts', 'native-tables-charts' ); ?></option></select><label for="ntc-post-type-<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Post type', 'native-tables-charts' ); ?></label><input id="ntc-post-type-<?php echo esc_attr( $id ); ?>" type="text" name="post_type" value="<?php echo esc_attr( $post_cfg['post_type'] ?? 'post' ); ?>" placeholder="post"><label for="ntc-meta-label-<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Meta key for row labels', 'native-tables-charts' ); ?></label><input id="ntc-meta-label-<?php echo esc_attr( $id ); ?>" type="text" name="meta_label" value="<?php echo esc_attr( $post_cfg['meta_label'] ?? '' ); ?>" placeholder="product_name"><label for="ntc-meta-value-<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Meta keys for values', 'native-tables-charts' ); ?></label><input id="ntc-meta-value-<?php echo esc_attr( $id ); ?>" type="text" name="meta_value" value="<?php echo esc_attr( implode( ',', (array) ( $post_cfg['meta_value'] ?? array() ) ) ); ?>" placeholder="score, rating" aria-describedby="ntc-meta-value-help-<?php echo esc_attr( $id ); ?>"><span id="ntc-meta-value-help-<?php echo esc_attr( $id ); ?>" class="description"><?php esc_html_e( 'Separate multiple keys with commas.', 'native-tables-charts' ); ?></span><button class="button"><?php esc_html_e( 'Save posts source', 'native-tables-charts' ); ?></button></form></section><?php endif; ?>
			<section class="ntc-action-group ntc-danger-zone"><h3><?php esc_html_e( 'Delete dataset', 'native-tables-charts' ); ?></h3><p><?php esc_html_e( 'Also deletes its synced views. This cannot be undone.', 'native-tables-charts' ); ?></p><form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this dataset and its synced views? This cannot be undone.', 'native-tables-charts' ) ); ?>')"><?php wp_nonce_field( 'ntc_admin_action' ); ?><input type="hidden" name="ntc_action" value="delete_dataset"><input type="hidden" name="dataset_id" value="<?php echo esc_attr( $id ); ?>"><button class="button button-link-delete"><?php esc_html_e( 'Delete dataset', 'native-tables-charts' ); ?></button></form></section>
			</div></details></td></tr><?php endforeach; ?>
		</tbody></table></div>
		<?php
	}

	public function presets_page(): void {
		$custom = $this->repo->list_presets();
		$tables = NTC_Renderer::table_presets();
		$charts = NTC_Renderer::chart_presets();
		?>
		<div class="wrap ntc-admin"><h1><?php esc_html_e( 'Style Presets', 'native-tables-charts' ); ?></h1><p><?php esc_html_e( 'Built-in presets are always available in Gutenberg. Editors can also save the current table or chart style as a custom preset from the block toolbar.', 'native-tables-charts' ); ?></p>
		<?php
		if ( isset( $_GET['import_error'] ) ) :
			?>
			<div class="notice notice-error inline"><p><?php esc_html_e( 'The preset could not be imported. Use a Native Tables & Charts preset JSON file.', 'native-tables-charts' ); ?></p></div><?php endif; ?>
		<form method="post" enctype="multipart/form-data" class="ntc-preset-import"><?php wp_nonce_field( 'ntc_admin_action' ); ?><input type="hidden" name="ntc_action" value="import_preset"><div class="ntc-file-field"><label for="ntc-preset-file"><?php esc_html_e( 'Style preset JSON', 'native-tables-charts' ); ?></label><input id="ntc-preset-file" type="file" name="preset_file" accept=".json,application/json" required aria-describedby="ntc-preset-file-help"><p id="ntc-preset-file-help" class="ntc-file-hint"><?php esc_html_e( 'Preset file exported by this plugin. Maximum size: 2 MB.', 'native-tables-charts' ); ?></p></div><button class="button"><?php esc_html_e( 'Import preset', 'native-tables-charts' ); ?></button></form>
		<div class="ntc-cards"><section><h2><?php esc_html_e( 'Built-in Table Styles', 'native-tables-charts' ); ?></h2>
		<?php
		foreach ( $tables as $slug => $settings ) :
			?>
			<div class="ntc-preset-row"><div class="ntc-preset-name"><strong><?php echo esc_html( ucwords( str_replace( '-', ' ', $slug ) ) ); ?></strong><code><?php echo esc_html( $slug ); ?></code></div><div class="ntc-preset-swatch ntc-preset-swatch-table" aria-hidden="true" style="background:<?php echo esc_attr( $settings['oddBackground'] ?? '#fff' ); ?>"><span style="background:<?php echo esc_attr( $settings['headerBackground'] ?? '#151922' ); ?>"></span><span style="background:<?php echo esc_attr( $settings['oddBackground'] ?? '#fff' ); ?>"></span><span style="background:<?php echo esc_attr( $settings['evenBackground'] ?? '#f6f7f8' ); ?>"></span><span style="background:<?php echo esc_attr( $settings['oddBackground'] ?? '#fff' ); ?>"></span></div></div><?php endforeach; ?></section><section><h2><?php esc_html_e( 'Built-in Chart Styles', 'native-tables-charts' ); ?></h2>
			<?php
			foreach ( $charts as $slug => $settings ) :
				?>
			<div class="ntc-preset-row"><div class="ntc-preset-name"><strong><?php echo esc_html( ucwords( str_replace( '-', ' ', $slug ) ) ); ?></strong><code><?php echo esc_html( $slug ); ?></code></div><div class="ntc-preset-swatch ntc-preset-swatch-chart" aria-hidden="true" style="background:<?php echo esc_attr( $settings['background'] ?? '#09111b' ); ?>"><span style="width:88%;background:<?php echo esc_attr( $settings['primaryColor'] ?? '#624b8e' ); ?>"></span><span style="width:68%;background:<?php echo esc_attr( $settings['highlightColor'] ?? '#9e2f5f' ); ?>"></span><span style="width:50%;background:<?php echo esc_attr( $settings['secondaryColor'] ?? '#8b73b8' ); ?>"></span></div></div><?php endforeach; ?></section></div>
		<p class="ntc-inspector-note"><?php esc_html_e( 'Open any Table or Chart block in Gutenberg to apply a style or save a custom preset from its More menu.', 'native-tables-charts' ); ?></p>
		<h2><?php esc_html_e( 'Custom Presets', 'native-tables-charts' ); ?></h2><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Name', 'native-tables-charts' ); ?></th><th><?php esc_html_e( 'Type', 'native-tables-charts' ); ?></th><th><?php esc_html_e( 'Slug', 'native-tables-charts' ); ?></th><th></th></tr></thead><tbody>
		<?php
		if ( ! $custom ) :
			?>
			<tr><td colspan="4"><div class="ntc-preset-empty"><p><strong><?php esc_html_e( 'No custom presets yet.', 'native-tables-charts' ); ?></strong><br><?php esc_html_e( 'Style a table or chart in the editor, then open its More menu and choose Save style preset.', 'native-tables-charts' ); ?></p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php' ) ); ?>"><?php esc_html_e( 'Open the editor', 'native-tables-charts' ); ?></a></div></td></tr><?php endif; ?>
			<?php
			foreach ( $custom as $p ) :
				?>
			<tr><td><?php echo esc_html( $p['name'] ); ?></td><td><?php echo esc_html( $p['type'] ); ?></td><td><code><?php echo esc_html( $p['slug'] ); ?></code></td><td><form method="post" class="ntc-inline-form"><?php wp_nonce_field( 'ntc_admin_action' ); ?><input type="hidden" name="ntc_action" value="export_preset"><input type="hidden" name="preset_id" value="<?php echo esc_attr( $p['id'] ); ?>"><button class="button"><?php esc_html_e( 'Export', 'native-tables-charts' ); ?></button></form> <form method="post" class="ntc-inline-form" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this custom preset? Existing blocks keep their saved appearance, but the preset cannot be restored.', 'native-tables-charts' ) ); ?>')"><?php wp_nonce_field( 'ntc_admin_action' ); ?><input type="hidden" name="ntc_action" value="delete_preset"><input type="hidden" name="preset_id" value="<?php echo esc_attr( $p['id'] ); ?>"><button class="button button-link-delete"><?php esc_html_e( 'Delete', 'native-tables-charts' ); ?></button></form></td></tr><?php endforeach; ?></tbody></table></div>
		<?php
	}

	public function tools_page(): void {
		$result = get_transient( 'ntc_tools_result_' . get_current_user_id() );
		?>
		<div class="wrap ntc-admin"><h1><?php esc_html_e( 'Import & Export', 'native-tables-charts' ); ?></h1><p><?php esc_html_e( 'Create a complete portable backup of reusable datasets, synced table/chart views and custom presets, or import a backup created by this plugin.', 'native-tables-charts' ); ?></p>
		<div class="ntc-cards"><section><h2><?php esc_html_e( 'Export Complete Backup', 'native-tables-charts' ); ?></h2><p><?php esc_html_e( 'Exports all reusable datasets and their synced views. Custom presets are included when you have permission to manage presets. Post content is not changed.', 'native-tables-charts' ); ?></p><form method="post"><?php wp_nonce_field( 'ntc_admin_action' ); ?><input type="hidden" name="ntc_action" value="export_all_bundle"><button class="button button-primary"><?php esc_html_e( 'Download Complete JSON Backup', 'native-tables-charts' ); ?></button></form></section>
		<section><h2><?php esc_html_e( 'Import Native Backup', 'native-tables-charts' ); ?></h2><p><?php esc_html_e( 'Imports complete backups, single-dataset bundles, or raw Native Tables & Charts JSON data exports. Imported items are created as new records; existing datasets are not overwritten.', 'native-tables-charts' ); ?></p><form method="post" enctype="multipart/form-data"><?php wp_nonce_field( 'ntc_admin_action' ); ?><input type="hidden" name="ntc_action" value="import_native_bundle"><div class="ntc-file-field"><label for="ntc-native-bundle"><?php esc_html_e( 'Native Tables & Charts backup', 'native-tables-charts' ); ?></label><input id="ntc-native-bundle" type="file" name="native_bundle" accept=".json,application/json" required aria-describedby="ntc-native-bundle-help"><p id="ntc-native-bundle-help" class="ntc-file-hint"><?php esc_html_e( 'JSON file exported by this plugin. Maximum size: 50 MB.', 'native-tables-charts' ); ?></p></div><p><button class="button"><?php esc_html_e( 'Import backup', 'native-tables-charts' ); ?></button></p></form></section></div>
		<?php
		if ( is_array( $result ) ) :
			$import_success = ! empty( $result['success'] );
			?>
			<div class="ntc-import-result"><div class="notice <?php echo $import_success ? 'notice-success' : 'notice-error'; ?> inline"><p><strong><?php echo esc_html( $import_success ? __( 'Import completed', 'native-tables-charts' ) : __( 'We couldn’t import this file', 'native-tables-charts' ) ); ?></strong></p><p><?php echo esc_html( $import_success ? __( 'The backup was processed. Review the imported datasets in Data Library.', 'native-tables-charts' ) : ( $result['error'] ?? __( 'Check that this is a Native Tables & Charts JSON backup, then try again.', 'native-tables-charts' ) ) ); ?></p><?php if ( $import_success ) : ?><p><a href="<?php echo esc_url( admin_url( 'admin.php?page=ntc-data' ) ); ?>"><?php esc_html_e( 'Open Data Library', 'native-tables-charts' ); ?></a></p><?php endif; ?></div><details class="ntc-technical-details"><summary><?php esc_html_e( 'Technical details', 'native-tables-charts' ); ?></summary><pre class="ntc-result"><?php echo esc_html( wp_json_encode( $result, JSON_PRETTY_PRINT ) ); ?></pre></details></div><?php endif; ?></div>
		<?php
	}

	public function migration_page(): void {
		$uid                = get_current_user_id();
		$migration_progress = get_transient( 'ntc_migration_progress_' . $uid );
		if ( is_array( $migration_progress ) && NTC_Migrator::MIGRATION_STATE_VERSION !== (int) ( $migration_progress['state_version'] ?? 0 ) ) {
			$this->migrator->clear_migration_state( (string) ( $migration_progress['batch_id'] ?? '' ) );
			delete_transient( 'ntc_migration_progress_' . $uid );
			delete_transient( 'ntc_migration_targets_pending_' . $uid );
			$migration_progress = false;
		}
		$active_migration   = is_array( $migration_progress ) && empty( $migration_progress['done'] );
		$detection_state    = $active_migration ? false : get_transient( 'ntc_migration_detection_' . $uid );
		if ( ! $active_migration && ! is_array( $detection_state ) ) {
			$detection_state = $this->migrator->detect_batch( array(), 0 );
			set_transient( 'ntc_migration_detection_' . $uid, $detection_state, HOUR_IN_SECONDS );
		}
		$detection_complete = $active_migration || ( is_array( $detection_state ) && ! empty( $detection_state['complete'] ) );
		$detect             = $active_migration ? $this->migrator->detect( false, false ) : (array) $detection_state;
		if ( $active_migration ) {
			$detect['posts']     = (int) ( $migration_progress['total'] ?? 0 );
			$detect['instances'] = (int) ( $migration_progress['instance_total'] ?? 0 );
		} elseif ( $detection_complete ) {
			set_transient(
				'ntc_migration_targets_pending_' . $uid,
				array(
					'post_ids'  => (array) ( $detect['post_ids'] ?? array() ),
					'instances' => (int) $detect['instances'],
				),
				30 * MINUTE_IN_SECONDS
			);
		}
		unset( $detect['post_ids'] );
		$report             = get_transient( 'ntc_migration_report_' . get_current_user_id() );
		$result             = get_transient( 'ntc_migration_result_' . get_current_user_id() );
		$rollback_progress  = get_transient( 'ntc_rollback_progress_' . get_current_user_id() );
		?>
		<div class="wrap ntc-admin"><h1><?php esc_html_e( 'League Table Migration', 'native-tables-charts' ); ?></h1><div class="ntc-callout <?php echo $detect['available'] ? 'is-good' : 'is-neutral'; ?>"><strong><?php echo $detect['available'] ? esc_html__( 'League Table data detected.', 'native-tables-charts' ) : esc_html__( 'No migration is required.', 'native-tables-charts' ); ?></strong>
		<?php
		if ( $detect['available'] ) :
			?>
			<?php /* translators: 1: number of tables, 2: number of posts, 3: number of shortcode/block instances. */ ?>
			<p id="ntc-detection-summary"><?php printf( esc_html__( '%1$d tables, %2$d posts and %3$d shortcode/block instances were found.', 'native-tables-charts' ), $detect['tables'], $detect['posts'], $detect['instances'] ); ?></p><?php else : ?><p><?php esc_html_e( 'No League Table data was detected on this site. You can still import an XML backup from another site below.', 'native-tables-charts' ); ?></p><?php endif; ?></div>
		<?php if ( $detect['available'] && ! $detection_complete ) : ?>
		<div class="notice notice-info" id="ntc-detection-progress"><p><?php esc_html_e( 'Scanning post content in short, bounded requests. Migration will process only the posts identified here.', 'native-tables-charts' ); ?> <span id="ntc-detection-status"><?php printf( esc_html__( 'Scanned through post ID %1$d of %2$d.', 'native-tables-charts' ), (int) ( $detect['cursor'] ?? 0 ), (int) ( $detect['max_id'] ?? 0 ) ); ?></span></p></div>
		<script>(function(){var nonce=<?php echo wp_json_encode( wp_create_nonce( 'ntc_migration_detect' ) ); ?>,url=<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;function next(){var body=new URLSearchParams({action:'ntc_migration_detect',nonce:nonce});fetch(url,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:body.toString()}).then(function(response){return response.json();}).then(function(result){if(!result.success)throw new Error();var d=result.data,summary=document.getElementById('ntc-detection-summary'),status=document.getElementById('ntc-detection-status');if(summary)summary.textContent=d.tables+' tables, '+d.posts+' posts and '+d.instances+' shortcode/block instances were found.';if(status)status.textContent='Scanned through post ID '+d.cursor+' of '+d.max_id+'.';if(d.complete){window.location.reload();return;}setTimeout(next,250);}).catch(function(){var status=document.getElementById('ntc-detection-status');if(status)status.textContent=<?php echo wp_json_encode( __( 'Detection paused after a request failed. Reload this page to resume safely.', 'native-tables-charts' ) ); ?>;});}next();})();</script>
		<?php endif; ?>
		<?php
		if ( $detect['available'] && $detection_complete ) :
			?>
			<div class="ntc-cards"><section><h2><?php esc_html_e( '1. Dry Run', 'native-tables-charts' ); ?></h2><p><?php esc_html_e( 'Analyze the existing League Table database before changing anything.', 'native-tables-charts' ); ?></p><form method="post"><?php wp_nonce_field( 'ntc_admin_action' ); ?><input type="hidden" name="ntc_action" value="migration_dry_run"><button class="button button-secondary"><?php esc_html_e( 'Run Dry-Run Report', 'native-tables-charts' ); ?></button></form></section><section><h2><?php esc_html_e( '2. Migrate', 'native-tables-charts' ); ?></h2><p><?php esc_html_e( 'Create reusable Native Tables datasets and views. Existing League Table database tables are never deleted.', 'native-tables-charts' ); ?></p><form method="post" id="ntc-migrate-form" onsubmit="return this.elements.batch_id.value!==''||confirm('<?php echo esc_js( __( 'Start the migration now? A backup of every changed post will be kept for rollback.', 'native-tables-charts' ) ); ?>')"><?php wp_nonce_field( 'ntc_admin_action' ); ?><input type="hidden" name="ntc_action" value="migration_run"><label><input type="checkbox" name="convert_content" value="1" <?php checked( ! is_array( $migration_progress ) || ! empty( $migration_progress['convert'] ) ); ?>> <?php esc_html_e( 'Replace [lt] shortcodes and dalt/table blocks in post content', 'native-tables-charts' ); ?></label><input type="hidden" name="batch_id" value="<?php echo esc_attr( $migration_progress['batch_id'] ?? '' ); ?>"><input type="hidden" name="offset" value="<?php echo esc_attr( $migration_progress['offset'] ?? '0' ); ?>"><input type="hidden" name="cursor" value="<?php echo esc_attr( $migration_progress['cursor'] ?? '0' ); ?>"><input type="hidden" name="target_offset" value="<?php echo esc_attr( $migration_progress['target_offset'] ?? '0' ); ?>"><input type="hidden" name="table_cursor" value="<?php echo esc_attr( $migration_progress['table_cursor'] ?? '0' ); ?>"><p><button class="button button-primary"><?php esc_html_e( 'Run Migration', 'native-tables-charts' ); ?></button></p></form></section></div><?php endif; ?>
		<div class="ntc-cards"><section><h2><?php esc_html_e( 'Import League Table XML', 'native-tables-charts' ); ?></h2><p><?php esc_html_e( 'If you exported tables from League Table on another site, import its XML backup directly into Native Tables & Charts. This creates reusable datasets and synced table views without requiring the old plugin to be active.', 'native-tables-charts' ); ?></p><form method="post" enctype="multipart/form-data"><?php wp_nonce_field( 'ntc_admin_action' ); ?><input type="hidden" name="ntc_action" value="migration_xml_import"><div class="ntc-file-field"><label for="ntc-league-xml"><?php esc_html_e( 'League Table XML backup', 'native-tables-charts' ); ?></label><input id="ntc-league-xml" type="file" name="league_xml" accept=".xml,text/xml,application/xml" required aria-describedby="ntc-league-xml-help"><p id="ntc-league-xml-help" class="ntc-file-hint"><?php esc_html_e( 'XML file exported by League Table. Maximum size: 20 MB.', 'native-tables-charts' ); ?></p></div><p><button class="button"><?php esc_html_e( 'Import XML backup', 'native-tables-charts' ); ?></button></p></form></section></div>
		<?php
		if ( is_array( $report ) ) :
			?>
			<h2><?php esc_html_e( 'Dry-Run Report', 'native-tables-charts' ); ?></h2><table class="widefat striped ntc-report"><tbody>
			<?php
			foreach ( array(
				'tables'         => 'Tables',
				'posts'          => 'Posts using League Table',
				'instances'      => 'Shortcode/block instances',
				'formula_tables' => 'Tables using formulas',
				'merged_tables'  => 'Tables using merged cells',
				'html_cells'     => 'HTML cells',
				'image_cells'    => 'Image cells',
				'link_cells'     => 'Linked cells',
			) as $k => $label ) :
				?>
	<tr><th><?php echo esc_html( $label ); ?></th><td><?php echo esc_html( $report[ $k ] ?? 0 ); ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
		<?php
		if ( is_array( $result ) || ! empty( $rollback_progress['batch_id'] ) ) :
			if ( is_array( $result ) ) :
				?>
			<h2><?php esc_html_e( 'Latest Migration Result', 'native-tables-charts' ); ?></h2><pre class="ntc-result"><?php echo esc_html( wp_json_encode( $result, JSON_PRETTY_PRINT ) ); ?></pre>
				<?php
			endif;
			if ( ! empty( $result['batch_id'] ) || ! empty( $rollback_progress['batch_id'] ) ) :
				?>
			<form method="post" id="ntc-rollback-form" onsubmit="return this.elements.offset.value!=0||confirm('<?php echo esc_js( __( 'Restore the original post content from this migration batch?', 'native-tables-charts' ) ); ?>')"><?php wp_nonce_field( 'ntc_admin_action' ); ?><input type="hidden" name="ntc_action" value="migration_rollback"><input type="hidden" name="batch_id" value="<?php echo esc_attr( ( $rollback_progress['batch_id'] ?? ( $result['batch_id'] ?? '' ) ) ); ?>"><input type="hidden" name="offset" value="<?php echo esc_attr( $rollback_progress['offset'] ?? '0' ); ?>"><button class="button"><?php esc_html_e( 'Rollback This Batch', 'native-tables-charts' ); ?></button></form>
				<?php
			endif;
		endif;
		?>
		<?php if ( is_array( $migration_progress ) && empty( $migration_progress['done'] ) ) : ?>
		<form method="post" id="ntc-migration-reset-form"><?php wp_nonce_field( 'ntc_admin_action' ); ?><input type="hidden" name="ntc_action" value="migration_reset"><input type="hidden" name="batch_id" value="<?php echo esc_attr( $migration_progress['batch_id'] ?? '' ); ?>"><button class="button button-secondary"><?php esc_html_e( 'Restart Migration Detection', 'native-tables-charts' ); ?></button></form>
			<?php if ( ! empty( $migration_progress['errors'] ) ) : ?>
		<div class="notice notice-error"><p><?php esc_html_e( 'Migration paused after an error. Use Run Migration to retry this step, or restart detection to begin a clean batch.', 'native-tables-charts' ); ?></p><p><code><?php echo esc_html( (string) $migration_progress['errors'][ array_key_last( $migration_progress['errors'] ) ] ); ?></code></p></div>
			<?php endif; ?>
			<?php if ( 'tables' === ( $migration_progress['phase'] ?? 'tables' ) ) : ?>
				<?php if ( ! empty( $migration_progress['current_table'] ) ) : ?>
					<?php /* translators: 1: legacy table ID, 2: current import stage, 3: completed legacy tables, 4: total legacy tables. */ ?>
		<div class="notice notice-info"><p><?php printf( esc_html__( 'Migration in progress — table %1$d, importing %2$s. %3$d of %4$d legacy tables completed. Keep this tab open.', 'native-tables-charts' ), (int) $migration_progress['current_table'], 'cells' === ( $migration_progress['table_stage'] ?? '' ) ? esc_html__( 'cell properties', 'native-tables-charts' ) : esc_html__( 'rows', 'native-tables-charts' ), (int) $migration_progress['table_offset'], (int) $migration_progress['table_total'] ); ?></p></div>
				<?php else : ?>
					<?php /* translators: 1: number of legacy tables completed so far, 2: total legacy tables. */ ?>
		<div class="notice notice-info"><p><?php printf( esc_html__( 'Migration in progress — %1$d of %2$d legacy tables completed. Keep this tab open.', 'native-tables-charts' ), (int) $migration_progress['table_offset'], (int) $migration_progress['table_total'] ); ?></p></div>
				<?php endif; ?>
			<?php else : ?>
				<?php /* translators: 1: number of identified posts processed so far, 2: total identified posts. */ ?>
		<div class="notice notice-info"><p><?php printf( esc_html__( 'Migration in progress — %1$d of %2$d identified posts processed. Keep this tab open.', 'native-tables-charts' ), (int) $migration_progress['offset'], (int) $migration_progress['total'] ); ?></p></div>
			<?php endif; ?>
			<?php if ( empty( $migration_progress['errors'] ) ) : ?>
		<script>(function(){setTimeout(function(){var f=document.getElementById('ntc-migrate-form');if(f){(f.requestSubmit||f.submit).call(f);}},2000);})();</script>
			<?php endif; ?>
		<?php endif; ?>
		<?php if ( is_array( $rollback_progress ) && empty( $rollback_progress['done'] ) ) : ?>
			<?php /* translators: 1: number of posts restored so far, 2: total posts to restore. */ ?>
		<div class="notice notice-info"><p><?php printf( esc_html__( 'Rollback in progress — %1$d of %2$d posts restored. Keep this tab open.', 'native-tables-charts' ), (int) $rollback_progress['offset'], (int) $rollback_progress['total'] ); ?></p></div>
		<script>(function(){setTimeout(function(){var f=document.getElementById('ntc-rollback-form');if(f){(f.requestSubmit||f.submit).call(f);}},600);})();</script>
		<?php endif; ?>
		</div>
		<?php
	}

	public function settings_page(): void {
		$defaults = NTC_Renderer::cell_feature_defaults();
		$saved    = array_merge( $defaults, (array) get_option( 'ntc_cell_features', array() ) );
		$labels   = array(
			'textColor'        => __( 'Text colour', 'native-tables-charts' ),
			'backgroundColor'  => __( 'Background colour', 'native-tables-charts' ),
			'alignment'        => __( 'Alignment', 'native-tables-charts' ),
			'fontWeight'       => __( 'Font weight', 'native-tables-charts' ),
			'fontStyle'        => __( 'Font style', 'native-tables-charts' ),
			'link'             => __( 'Cell link', 'native-tables-charts' ),
			'linkColor'        => __( 'Link colour', 'native-tables-charts' ),
			'openLinkNewTab'   => __( 'Open cell link in new tab', 'native-tables-charts' ),
			'imageLeft'        => __( 'Left image', 'native-tables-charts' ),
			'imageLeftLink'    => __( 'Left image link', 'native-tables-charts' ),
			'imageLeftNewTab'  => __( 'Open left image link in new tab', 'native-tables-charts' ),
			'imageRight'       => __( 'Right image', 'native-tables-charts' ),
			'imageRightLink'   => __( 'Right image link', 'native-tables-charts' ),
			'imageRightNewTab' => __( 'Open right image link in new tab', 'native-tables-charts' ),
			'formula'          => __( 'Formula', 'native-tables-charts' ),
			'formulaData'      => __( 'Formula data', 'native-tables-charts' ),
			'html'             => __( 'Custom HTML', 'native-tables-charts' ),
			'rowSpan'          => __( 'Row span / merging', 'native-tables-charts' ),
			'columnSpan'       => __( 'Column span / merging', 'native-tables-charts' ),
		);
		$groups = array(
			'appearance' => array(
				'title'       => __( 'Appearance', 'native-tables-charts' ),
				'description' => __( 'Control text, background colour, alignment and font styling.', 'native-tables-charts' ),
				'keys'        => array( 'textColor', 'backgroundColor', 'alignment', 'fontWeight', 'fontStyle' ),
			),
			'links'      => array(
				'title'       => __( 'Links', 'native-tables-charts' ),
				'description' => __( 'Add links to cells and control their colour and opening behaviour.', 'native-tables-charts' ),
				'keys'        => array( 'link', 'linkColor', 'openLinkNewTab' ),
			),
			'images'     => array(
				'title'       => __( 'Images', 'native-tables-charts' ),
				'description' => __( 'Place images on either side of cell content, with optional links.', 'native-tables-charts' ),
				'keys'        => array( 'imageLeft', 'imageLeftLink', 'imageLeftNewTab', 'imageRight', 'imageRightLink', 'imageRightNewTab' ),
			),
			'formulas'   => array(
				'title'       => __( 'Formulas and HTML', 'native-tables-charts' ),
				'description' => __( 'Enable calculated content and sanitized custom HTML.', 'native-tables-charts' ),
				'keys'        => array( 'formula', 'formulaData', 'html' ),
			),
			'merging'    => array(
				'title'       => __( 'Merging', 'native-tables-charts' ),
				'description' => __( 'Merge cells across rows or columns.', 'native-tables-charts' ),
				'keys'        => array( 'rowSpan', 'columnSpan' ),
			),
		);
		?>
		<div class="wrap ntc-admin"><h1><?php esc_html_e( 'Native Tables & Charts Settings', 'native-tables-charts' ); ?></h1>
		<?php
		if ( isset( $_GET['saved'] ) ) :
			?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'native-tables-charts' ); ?></p></div><?php endif; ?><form method="post"><?php wp_nonce_field( 'ntc_admin_action' ); ?><input type="hidden" name="ntc_action" value="save_settings">
		<h2><?php esc_html_e( 'Editor Cell Properties', 'native-tables-charts' ); ?></h2><p><?php esc_html_e( 'Choose which advanced cell controls are shown to editors. Existing saved cell properties continue to render even when a control is hidden.', 'native-tables-charts' ); ?></p>
		<div id="ntc-cell-feature-settings">
		<?php foreach ( $groups as $group_id => $group ) : ?>
			<section class="ntc-setting-group" aria-labelledby="ntc-setting-title-<?php echo esc_attr( $group_id ); ?>">
				<h3 id="ntc-setting-title-<?php echo esc_attr( $group_id ); ?>"><?php echo esc_html( $group['title'] ); ?></h3>
				<p><?php echo esc_html( $group['description'] ); ?></p>
				<div class="ntc-setting-actions"><button type="button" class="button" data-ntc-setting-toggle="1" aria-controls="ntc-setting-grid-<?php echo esc_attr( $group_id ); ?>"><?php esc_html_e( 'Enable all', 'native-tables-charts' ); ?></button><button type="button" class="button" data-ntc-setting-toggle="0" aria-controls="ntc-setting-grid-<?php echo esc_attr( $group_id ); ?>"><?php esc_html_e( 'Disable all', 'native-tables-charts' ); ?></button></div>
				<div class="ntc-setting-grid" id="ntc-setting-grid-<?php echo esc_attr( $group_id ); ?>">
				<?php foreach ( $group['keys'] as $key ) : ?>
					<label><input type="checkbox" name="cell_features[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $saved[ $key ] ) ); ?>> <?php echo esc_html( $labels[ $key ] ); ?></label>
				<?php endforeach; ?>
				</div>
			</section>
		<?php endforeach; ?>
		</div>
		<h2><?php esc_html_e( 'Custom HTML Safety', 'native-tables-charts' ); ?></h2><p><?php esc_html_e( "Custom HTML cells are always sanitized. Leave these fields blank to use WordPress' normal allowed post HTML and protocols, or enter a League Table-style restricted allow-list.", 'native-tables-charts' ); ?></p><table class="form-table"><tr><th scope="row"><label for="ntc-kses-tags"><?php esc_html_e( 'Allowed HTML tags', 'native-tables-charts' ); ?></label></th><td><input id="ntc-kses-tags" class="regular-text" type="text" name="kses_allowed_html_tags" value="<?php echo esc_attr( (string) get_option( 'ntc_kses_allowed_html_tags', '' ) ); ?>" placeholder="a[href][title], br, em, strong"><p class="description"><?php esc_html_e( 'Example: a[href][title], br, em, strong', 'native-tables-charts' ); ?></p></td></tr><tr><th scope="row"><label for="ntc-kses-protocols"><?php esc_html_e( 'Allowed link protocols', 'native-tables-charts' ); ?></label></th><td><input id="ntc-kses-protocols" class="regular-text" type="text" name="kses_allowed_protocols" value="<?php echo esc_attr( (string) get_option( 'ntc_kses_allowed_protocols', '' ) ); ?>" placeholder="http, https, mailto"></td></tr></table>
		<h2><?php esc_html_e( 'Uninstall', 'native-tables-charts' ); ?></h2><table class="form-table"><tr><th scope="row"><?php esc_html_e( 'Uninstall behaviour', 'native-tables-charts' ); ?></th><td><label><input type="checkbox" name="delete_data" value="1" <?php checked( (bool) get_option( 'ntc_delete_data_on_uninstall', false ) ); ?>> <?php esc_html_e( 'Delete Native Tables & Charts datasets, views, presets and migration backups when the plugin is uninstalled. Leave this off on production sites unless you intentionally want to remove all plugin data.', 'native-tables-charts' ); ?></label></td></tr></table><?php submit_button(); ?>
		<script>(function(){var container=document.getElementById('ntc-cell-feature-settings');if(!container){return;}container.addEventListener('click',function(event){var button=event.target.closest('[data-ntc-setting-toggle]');if(!button||!container.contains(button)){return;}var checked=button.getAttribute('data-ntc-setting-toggle')==='1';button.closest('.ntc-setting-group').querySelectorAll('input[type="checkbox"]').forEach(function(input){input.checked=checked;});});})();</script>
		</form></div>
		<?php
	}
}
