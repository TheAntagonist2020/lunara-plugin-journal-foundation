<?php
/**
 * Optional, redacted Site Studio workflow handoff.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Lunara_Journal_Site_Studio {
	const SURFACE_ID = 'journal-workflow';

	public static function bootstrap() {
		add_filter( 'lunara_site_studio_surfaces', array( __CLASS__, 'contribute_surfaces' ) );
	}

	public static function contribute_surfaces( $surfaces ) {
		$surfaces = is_array( $surfaces ) ? $surfaces : array();
		$path = Lunara_Journal_Control_Plane::admin_path();
		$surfaces[ self::SURFACE_ID ] = array(
			'id'                    => self::SURFACE_ID,
			'group'                 => __( 'Journal', 'lunara-journal-foundation' ),
			'label'                 => __( 'Journal Workflow', 'lunara-journal-foundation' ),
			'description'           => __( 'Manage Journal automation, editorial guidance, labeled sources, and version history.', 'lunara-journal-foundation' ),
			'aliases'               => array( 'journal control plane', 'sources', 'schedule', 'editorial workflow' ),
			'owner'                 => 'plugin:lunara-journal-foundation',
			'kind'                  => 'workflow',
			'capability'            => Lunara_Journal_Control_Plane::CAPABILITY,
			'supports_preview'      => false,
			'preview_route'         => '',
			'preview_query_arg'     => '',
			'adapter_factory'       => '',
			'state_schema_callback' => '',
			'admin_url'             => $path,
			'dependency_callback'   => array( __CLASS__, 'dependency_status' ),
			'status_callback'       => 'lunara_journal_foundation_workflow_status',
			'danger_level'          => 'none',
			'sections'              => array( 'workflow', 'editorial-guidance', 'sources', 'version-history' ),
			'classic_url'           => $path,
			'renderer'              => '',
		);
		return $surfaces;
	}

	public static function dependency_status( $surface = array() ) {
		unset( $surface );
		return array(
			'available' => true,
			'message'   => __( 'Journal Foundation owns the active workflow configuration.', 'lunara-journal-foundation' ),
		);
	}

	public static function workflow_status( $surface = array() ) {
		unset( $surface );
		$active_id = Lunara_Journal_Config_Repository::get_active_version_id();
		$version = $active_id ? Lunara_Journal_Config_Repository::get_version( $active_id ) : null;
		$config = is_array( $version ) && isset( $version['config'] ) && is_array( $version['config'] ) ? $version['config'] : array();
		$validation = empty( $config ) ? array( 'valid' => false ) : Lunara_Journal_Config_Schema::validate_config( $config );
		$enabled_sources = 0;
		foreach ( isset( $config['sources'] ) && is_array( $config['sources'] ) ? $config['sources'] : array() as $source ) {
			if ( is_array( $source ) && ! empty( $source['enabled'] ) ) {
				$enabled_sources++;
			}
		}
		$ready = ! empty( $validation['valid'] );
		return array(
			'state'        => $ready ? 'ready' : 'needs-attention',
			'label'        => $ready ? __( 'Workflow ready', 'lunara-journal-foundation' ) : __( 'Workflow needs attention', 'lunara-journal-foundation' ),
			'message'      => $ready
				? sprintf( __( 'Journal Foundation %s owns this workflow. %d sources are enabled.', 'lunara-journal-foundation' ), Lunara_Journal_Foundation::VERSION, $enabled_sources )
				: __( 'Journal Foundation owns this workflow, but the active configuration needs attention.', 'lunara-journal-foundation' ),
			'updated_at'   => is_array( $version ) && isset( $version['activated_at_gmt'] ) ? sanitize_text_field( $version['activated_at_gmt'] ) : '',
			'action_label' => __( 'Open Journal Control Plane', 'lunara-journal-foundation' ),
			'count'        => $enabled_sources,
			'url'          => lunara_journal_foundation_control_plane_admin_url(),
		);
	}
}

if ( ! function_exists( 'lunara_journal_foundation_control_plane_admin_url' ) ) {
	function lunara_journal_foundation_control_plane_admin_url() {
		return Lunara_Journal_Control_Plane::admin_url();
	}
}

if ( ! function_exists( 'lunara_journal_foundation_workflow_status' ) ) {
	function lunara_journal_foundation_workflow_status( $surface = array() ) {
		return Lunara_Journal_Site_Studio::workflow_status( $surface );
	}
}
