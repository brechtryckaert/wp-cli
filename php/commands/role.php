<?php

/**
 * Manage user roles.
 *
 * @package wp-cli
 */
class Role_Command extends WP_CLI_Command {

	private $fields = array(
		'name',
		'role'
	);

	/**
	 * List all roles.
	 *
	 * ## OPTIONS
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific object fields. Defaults to name,role.
	 *
	 * [--format=<format>]
	 * : Accepted values: table, csv, json, count. Default: table
	 *
	 * ## EXAMPLES
	 *
	 *     wp role list --fields=role --format=csv
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {
		global $wp_roles;

		$output_roles = array();
		foreach ( $wp_roles->roles as $key => $role ) {
			$output_role = new stdClass;

			$output_role->name = $role['name'];
			$output_role->role = $key;

			$output_roles[] = $output_role;
		}

		$formatter = new \WP_CLI\Formatter( $assoc_args, $this->fields );
		$formatter->display_items( $output_roles );
	}

	/**
	 * Check if a role exists.
	 *
	 * ##DESCRIPTION
	 *
	 * Will exit with status 0 if the role exists, 1 if it does not.
	 *
	 * ## OPTIONS
	 *
	 * <role-key>
	 * : The internal name of the role.
	 *
	 * ## EXAMPLES
	 *
	 *     wp role exists editor
	 */
	public function exists( $args ) {
		global $wp_roles;

		if ( ! in_array($args[0], array_keys( $wp_roles->roles ) ) ) {
			WP_CLI::error( "Role with ID $args[0] does not exist." );
		}

		WP_CLI::success( "Role with ID $args[0] exists." );
	}

	/**
	 * Create a new role.
	 *
	 * ## OPTIONS
	 *
	 * <role-key>
	 * : The internal name of the role.
	 *
	 * <role-name>
	 * : The publicly visible name of the role.
	 *
	 * ## EXAMPLES
	 *
	 *     wp role create approver Approver
	 *
	 *     wp role create productadmin "Product Administrator"
	 */
	public function create( $args ) {
		self::persistence_check();

		$role_key = array_shift( $args );
		$role_name = array_shift( $args );

		if ( empty( $role_key ) || empty( $role_name ) )
			WP_CLI::error( "Can't create role, insufficient information provided.");

		if ( ! add_role( $role_key, $role_name ) )
			WP_CLI::error( "Role couldn't be created." );
		else
			WP_CLI::success( sprintf( "Role with key %s created.", $role_key ) );
	}

	/**
	 * Delete an existing role.
	 *
	 * ## OPTIONS
	 *
	 * <role-key>
	 * : The internal name of the role.
	 *
	 * ## EXAMPLES
	 *
	 *     wp role delete approver
	 *
	 *     wp role delete productadmin
	 */
	public function delete( $args ) {
		global $wp_roles;

		self::persistence_check();

		$role_key = array_shift( $args );

		if ( empty( $role_key ) || ! isset( $wp_roles->roles[$role_key] ) )
			WP_CLI::error( "Role key not provided, or is invalid." );

		remove_role( $role_key );

		// Note: remove_role() doesn't indicate success or otherwise, so we have to
		// check ourselves
		if ( ! isset( $wp_roles->roles[$role_key] ) )
			WP_CLI::success( sprintf( "Role with key %s deleted.", $role_key ) );
		else
			WP_CLI::error( sprintf( "Role with key %s could not be deleted.", $role_key ) );

	}

	/**
	 * Reset any default role to default capabilities.
	 *
	 * ## OPTIONS
	 *
	 * <role-key>
	 * : The internal name of the role.
	 *
	 * ## EXAMPLES
	 *
	 *     wp role reset administrator
	 *
	 *     wp role reset author
	 */
	public function reset( $args ) {

		self::persistence_check();

		$role_key = array_shift( $args );

		if ( empty( $role_key ) )
			WP_CLI::error( "Role key not provided, or is invalid." );

		if ( ! function_exists( 'populate_roles' ) ) {
			require_once( ABSPATH.'wp-admin/includes/schema.php' );
		}

		// get our default roles
		$preserve = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );

		if ( 'all' == $role_key ) {
			foreach( $preserve as $role ) {
				remove_role( $role );
			}
			populate_roles();

			WP_CLI::success( 'All default roles reset.' );
			return;

		}

		// if key not found, bail
		$key = array_search( $role_key, $preserve );
		if ( false === $key ) {
			WP_CLI::error( 'Must specify a default role to reset.' );
		}

		// remove the one to reset
		unset( $preserve[ $key ] );

		// for the roles we're not resetting
		foreach( $preserve as $k => $role ) {
			/* save roles
			 * if get_role is null
			 * save role name for re-removal
			 */
			$roleobj = get_role( $role );
			$preserve[$k] = is_null( $roleobj ) ? $role : $roleobj;

			remove_role( $role );
		}

		remove_role( $role_key );

		// put back all default roles and capabilities
		populate_roles();

		// restore the preserved roles
		foreach( $preserve as $k => $roleobj ) {
			// re-remove after populating
			if ( is_a( $roleobj, 'WP_Role' ) ) {
				remove_role( $roleobj->name );
				add_role( $roleobj->name, ucwords( $roleobj->name ), $roleobj->capabilities );
			} else {
				// when not an object, that means the role didn't exist before
				remove_role( $roleobj );
			}
		}

		WP_CLI::success( sprintf( "Role with key %s reset.", $role_key ) );

	}

	private static function persistence_check() {
		global $wp_roles;

		if ( !$wp_roles->use_db )
			WP_CLI::error( "Role definitions are not persistent." );
	}
}

WP_CLI::add_command( 'role', 'Role_Command' );
