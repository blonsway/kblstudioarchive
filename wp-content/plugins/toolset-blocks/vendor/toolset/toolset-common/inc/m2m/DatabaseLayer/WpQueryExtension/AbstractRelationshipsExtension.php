<?php

namespace OTGS\Toolset\Common\Relationships\DatabaseLayer\WpQueryExtension;

use InvalidArgumentException;
use IToolset_Post;
use IToolset_Relationship_Definition;
use OTGS\Toolset\Common\Relationships\API\RelationshipRole;
use OTGS\Toolset\Common\Relationships\DatabaseLayer\DatabaseLayerFactory;
use Toolset_Element_Domain;
use Toolset_Element_Exception_Element_Doesnt_Exist;
use Toolset_Relationship_Role;
use Toolset_Relationship_Role_Child;
use Toolset_Relationship_Role_Parent;
use Toolset_Utils;
use WP_Post;
use WP_Query;

/**
 * Shared functionality for the toolset_relationships WP_Query extension
 * when relationships are active (non-legacy mode).
 *
 * @see \OTGS\Toolset\Common\WpQueryExtension\AbstractRelationshipsExtension
 * @since 4.0 (some code extracted from Version1\Toolset_Wp_Query_Adjustments_M2m)
 */
abstract class AbstractRelationshipsExtension extends \OTGS\Toolset\Common\WpQueryExtension\AbstractRelationshipsExtension {

	/** @var \Toolset_Element_Factory */
	protected $element_factory;

	/** @var \Toolset_Relationship_Definition_Repository */
	protected $definition_repository;

	/** @var DatabaseLayerFactory */
	protected $database_layer_factory;


	/**
	 * AbstractRelationshipsExtension constructor.
	 *
	 * @param \wpdb $wpdb
	 * @param \Toolset_Element_Factory $element_factory
	 * @param \Toolset_Relationship_Definition_Repository $definition_repository
	 * @param DatabaseLayerFactory $database_layer_factory
	 */
	public function __construct(
		\wpdb $wpdb,
		\Toolset_Element_Factory $element_factory,
		\Toolset_Relationship_Definition_Repository $definition_repository,
		DatabaseLayerFactory $database_layer_factory
	) {
		parent::__construct( $wpdb );

		$this->element_factory = $element_factory;
		$this->definition_repository = $definition_repository;
		$this->database_layer_factory = $database_layer_factory;
	}


	/**
	 * Get the actual JOIN clause for the whole toolset_relationships query argument.
	 *
	 * The output needs to be safe to append to a pre-existing JOIN statement.
	 *
	 * @param WP_Query $wp_query
	 *
	 * @return string
	 */
	abstract protected function get_join_clause( \WP_Query $wp_query );


	/**
	 * Get the WHERE clause for a given sub-query.
	 *
	 * The output needs to be safe to append to a pre-existing WHERE statement.
	 *
	 * @param WP_Query $wp_query
	 * @param string $relationship_slug
	 * @param RelationshipRole $role_to_return
	 * @param RelationshipRole $role_to_query_by
	 * @param IToolset_Post $related_to_post
	 *
	 * @return string
	 */
	abstract protected function get_where_clause(
		\WP_Query $wp_query,
		$relationship_slug,
		RelationshipRole $role_to_return,
		RelationshipRole $role_to_query_by,
		IToolset_Post $related_to_post
	);


	/**
	 * @inheritdoc
	 */
	public function initialize() {
		parent::initialize();

		add_action(
			'pre_get_posts',
			array( $this, 'process_legacy_meta_query' ),
			self::TIME_TO_STORE_RELATIONSHIPS_ARG - 1 // do not change this, third-party software might depend on it
		);

		add_filter( 'posts_where', array( $this, 'posts_where' ), 10, 2 );
		add_filter( 'posts_join', array( $this, 'posts_join' ), 10, 2 );
	}


	/**
	 * Add conditions to the WHERE clause.
	 *
	 * @param string $where
	 * @param WP_Query $wp_query
	 *
	 * @return string
	 */
	public function posts_where( $where, $wp_query ) {
		do_action( 'toolset_do_m2m_full_init' );

		if ( property_exists( $wp_query, self::RELATIONSHIP_QUERY_ARG ) ) {
			$where = $this->add_relationship_query_where( $where, $wp_query->{self::RELATIONSHIP_QUERY_ARG}, $wp_query );
		}

		return $where;
	}


	/**
	 * Add tables to the JOIN clause.
	 *
	 * @param string $join
	 * @param WP_Query $wp_query
	 *
	 * @return string
	 */
	public function posts_join( $join, $wp_query ) {
		do_action( 'toolset_do_m2m_full_init' );

		if ( property_exists( $wp_query, self::RELATIONSHIP_QUERY_ARG ) ) {
			$join = $this->add_relationship_query_join( $join, $wp_query );
		}

		return $join;
	}


	/**
	 * @param string $where
	 * @param array $relationship_query
	 * @param WP_Query $wp_query
	 *
	 * @return string
	 */
	private function add_relationship_query_where( $where, $relationship_query, WP_Query $wp_query ) {
		$relationship_query = $this->normalize_relationship_query_args( $relationship_query );

		foreach ( $relationship_query as $query_condition ) {
			$where .= ' ' . $this->add_single_relationship_query_where_clause( $query_condition, $wp_query );
		}

		return $where;
	}


	/**
	 * @param $query_condition
	 * @param WP_Query $wp_query
	 *
	 * @return string
	 */
	private function add_single_relationship_query_where_clause( $query_condition, WP_Query $wp_query ) {
		$relationship_slug = $this->get_relationship_slug( $query_condition );
		$related_to_post = $this->get_post( $query_condition );

		if ( null === $relationship_slug || null === $related_to_post ) {
			// The relationship or the post doesn't exist, but it is not a misconfiguration of the
			// wp_query argument - we just return no results.
			return ' AND 0 = 1 ';
		}

		$role_to_return = $this->get_role( $query_condition, 'role' );
		$role_to_query_by = $this->get_role( $query_condition, 'role_to_query_by', $role_to_return );

		return $this->get_where_clause( $wp_query, $relationship_slug, $role_to_return, $role_to_query_by, $related_to_post );
	}


	private function add_relationship_query_join( $join, $wp_query ) {
		// Just add the tables from the JOIN manager which has been filled by data during the processing
		// of the posts_where filter (it comes before posts_join)
		return $join . ' ' . $this->get_join_clause( $wp_query ) . ' ';
	}


	/**
	 * Resolve the relationship slug from a given query condition.
	 *
	 * Also supports an array with a pair of post types that identify a legacy relationship.
	 *
	 * @param array $query_condition
	 *
	 * @return string
	 * @throws InvalidArgumentException
	 */
	private function get_relationship_slug( $query_condition ) {
		$relationship = toolset_getarr( $query_condition, 'relationship' );

		if ( is_array( $relationship ) ) {
			if ( count( $relationship ) !== 2 ) {
				throw new InvalidArgumentException( 'Invalid relationship query argument.' );
			}

			$relationship_definition = $this->definition_repository->get_legacy_definition(
				$relationship[0], $relationship[1]
			);
		} elseif ( ! is_string( $relationship ) || empty( $relationship ) ) {
			throw new InvalidArgumentException( 'Invalid relationship query argument.' );
		} else {
			$relationship_definition = $this->definition_repository->get_definition( $relationship );
		}

		if ( null === $relationship_definition ) {
			return null;
		}

		return $relationship_definition->get_slug();
	}


	/**
	 * Resolve the role object from the query condition.
	 *
	 * @param string[] $query_condition
	 * @param string $key Key of the element in the query condition that contains the role name.
	 * @param RelationshipRole|null $other_role If this is provided and is a parent or child,
	 *    the role in $key can be empty/other - the opposite of $other_role will be used in that case.
	 *
	 * @return RelationshipRole
	 */
	private function get_role( $query_condition, $key, RelationshipRole $other_role = null ) {
		$role_name = toolset_getarr( $query_condition, $key, 'other' );

		if ( 'other' === $role_name && $other_role instanceof RelationshipRole ) {
			return $other_role->other();
		}

		return Toolset_Relationship_Role::role_from_name( $role_name );
	}


	/**
	 * Get the "related_to" post from the query condition array.
	 *
	 * @param $query_condition
	 *
	 * @return IToolset_Post
	 */
	private function get_post( $query_condition ) {
		$related_to_post_id = toolset_getarr( $query_condition, 'related_to' );
		if ( $related_to_post_id instanceof WP_Post ) {
			$related_to_post_id = $related_to_post_id->ID;
		} elseif ( ! Toolset_Utils::is_natural_numeric( $related_to_post_id ) ) {
			throw new InvalidArgumentException( 'Invalid relationship query argument.' );
		} else {
			$related_to_post_id = (int) $related_to_post_id;
		}

		try {
			$post = $this->element_factory->get_post( $related_to_post_id );
		} catch ( Toolset_Element_Exception_Element_Doesnt_Exist $e ) {
			return null;
		}

		return $post;
	}


	/**
	 * Check the postmeta query arguments and if we detect an understandable usage of
	 * the legacy relationship postmeta, transform it into a toolset_relationships query.
	 *
	 * We check for:
	 * - meta_key and meta_value or meta_value_num
	 * - meta_query
	 *
	 * There are several limitations as to what we can parse:
	 *
	 * - Only legacy (migrated) relationships are supported.
	 * - It must be possible to determine a single relationship from the information passed to the query:
	 *     - The postmeta already contains the parent post type slug.
	 *     - For the child slug, either there's some information in the "post_type" query argument, or
	 *       we check against all post types.
	 *
	 *       For example, if there are legacy relationships between CPTS: A >> B, A >> C, B >> C,
	 *
	 *       we will always succeed with a meta_query for "_wpcf_belongs_b_id" (because there is only a
	 *       single relationship that has B post type as a parent), but we will succeed with
	 *       "_wpcf_belongs_a_id" only if the query also contains a post_type argument that doesn't contain
	 *       both B and C post types.
	 *
	 *       Non-legacy relationships are completely ignored here.
	 * - Only the topmost level of the 'meta_query' is processed, and we ignore anything nested.
	 * - 'meta_compare' argument or 'compare' within 'meta_query' must be '=' (or missing, since this is the default
	 * value)
	 * - 'relation' within 'meta_query' must be 'AND' (or missing).
	 *
	 * If we hit any of these limitations, we don't do anything and let the query run as-is.
	 *
	 * Otherwise, the condition is turned into a toolset_relationships one and removed.
	 *
	 * Note that we also remove meta_key together with meta_value/meta_value_num, which is necessary for the
	 * query to yield correct results. meta_key might be *theoretically* used for ordering, but it makes
	 * very little sense to order by IDs of parent post, so we take the risk.
	 *
	 * This is WPML-compatible and should yield results according to the allowed translation mode of post types
	 * involved in a relationship.
	 *
	 * @param WP_Query $query
	 *
	 * @since 2.6.4
	 */
	public function process_legacy_meta_query( $query ) {
		if ( ! $query instanceof WP_Query ) {
			// Something weird is happening.
			return;
		}

		$this->maybe_process_meta_value( $query );
		$this->maybe_process_meta_query( $query );
	}


	/**
	 * Try transforming meta_key + meta_value/meta_value_num into a toolset_relationships condition.
	 *
	 * @param WP_Query $query
	 *
	 * @since 2.6.4
	 */
	private function maybe_process_meta_value( WP_Query $query ) {
		if ( ! array_key_exists( 'meta_key', $query->query_vars ) ) {
			return;
		}

		$parent_post_type = $this->parse_legacy_meta_key( $query->query_vars['meta_key'] );
		if ( null === $parent_post_type ) {
			return; // not our legacy postmeta
		}

		if ( array_key_exists( 'meta_value_num', $query->query_vars ) ) {
			$post_id = (int) $query->query_vars['meta_value_num'];
		} elseif ( array_key_exists( 'meta_value', $query->query_vars ) ) {
			$post_id = (int) $query->query_vars['meta_value'];
		} else {
			return;
		}

		if (
			array_key_exists( 'meta_compare', $query->query_vars )
			&& '=' !== $query->query_vars['meta_compare']
		) {
			return;
		}

		$was_transformed = $this->try_transforming_legacy_meta_query( $query, $parent_post_type, $post_id );

		if ( $was_transformed ) {
			unset( $query->query_vars['meta_value'] );
			unset( $query->query_vars['meta_value_num'] );

			// Theoretically, meta_key could be also used for sorting, but it makes very little sense
			// to sort by IDs of parent posts.
			//
			// We need to remove it because it would otherwise cause an inner join on the
			// postmeta table with the legacy postmeta key, effectively excluding
			// all posts with associations created after the migration from legacy relationships.
			unset( $query->query_vars['meta_key'] );
		}
	}


	/**
	 * Try building a toolset_relationships condition out of the provided information.
	 *
	 * @param WP_Query $query
	 * @param string $parent_post_type
	 * @param int $parent_id
	 *
	 * @return bool True if the transformation took place, false if it couldn't be done.
	 * @since 2.6.4
	 */
	private function try_transforming_legacy_meta_query( WP_Query $query, $parent_post_type, $parent_id ) {
		if ( 0 === (int) $parent_id ) {
			return false;
		}

		do_action( 'toolset_do_m2m_full_init' );

		$relationship = $this->determine_relationship( $query, $parent_post_type );

		if ( null === $relationship ) {
			return false;
		}

		$relationship_query_arg = array(
			'role' => 'child', // legacy code had to be querying child posts
			'related_to' => (int) $parent_id,
			'relationship' => $relationship->get_slug(),
		);

		$this->add_relationship_query_condition( $query, $relationship_query_arg );

		return true;
	}


	/**
	 * Look for a single matching relationship definition.
	 *
	 * Use the post_type query argument, parent post type and is_legacy flag of the relationship definition.
	 *
	 * @param WP_Query $query
	 * @param $parent_post_type
	 *
	 * @return IToolset_Relationship_Definition|null Relationship definition or null if there are more than
	 *    one results or no results.
	 * @since 2.6.4
	 */
	private function determine_relationship( WP_Query $query, $parent_post_type ) {
		$child_post_types = toolset_getarr( $query->query_vars, 'post_type' );
		if ( ! is_array( $child_post_types ) ) {
			$child_post_types = array( $child_post_types );
		}
		if ( in_array( 'any', $child_post_types ) ) {
			$child_post_types = array();
		}

		$relationship_query = $this->database_layer_factory->relationship_query();
		$relationships = $relationship_query
			->add( $relationship_query->is_legacy() )
			->add( $relationship_query->has_domain_and_type(
				$parent_post_type, Toolset_Element_Domain::POSTS, new Toolset_Relationship_Role_Parent() )
			)
			->add( $relationship_query->has_domain( Toolset_Element_Domain::POSTS, new Toolset_Relationship_Role_Child() ) )
			->add(
			// we can afford to add empty or condition
				$relationship_query->do_or(
					array_map(
						function ( $child_post_type ) use ( $relationship_query ) {
							return $relationship_query->has_type( $child_post_type, new Toolset_Relationship_Role_Child() );
						}, $child_post_types
					)
				)
			)
			->get_results();

		if ( count( $relationships ) !== 1 ) {
			return null;
		}

		return array_pop( $relationships );
	}


	/**
	 * Add a single condition to the toolset_relationships query argument.
	 *
	 * @param WP_Query $query
	 * @param $condition
	 */
	private function add_relationship_query_condition( WP_Query $query, $condition ) {
		if ( ! array_key_exists( self::RELATIONSHIP_QUERY_ARG, $query->query_vars ) ) {
			$query->query_vars[ self::RELATIONSHIP_QUERY_ARG ] = array();
		} else {
			$query->query_vars[ self::RELATIONSHIP_QUERY_ARG ] = $this->normalize_relationship_query_args(
				$query->query_vars[ self::RELATIONSHIP_QUERY_ARG ]
			);
		}

		$query->query_vars[ self::RELATIONSHIP_QUERY_ARG ][] = $condition;
	}


	/**
	 * Try transforming a top level meta_query into toolset_relationships conditions.
	 *
	 * @param WP_Query $query
	 *
	 * @since 2.6.4
	 */
	private function maybe_process_meta_query( WP_Query $query ) {
		if ( ! array_key_exists( 'meta_query', $query->query_vars ) ) {
			return;
		}

		$meta_query = $query->query_vars['meta_query'];

		if ( ! is_array( $meta_query ) ) {
			return;
		}

		if ( 'AND' !== toolset_getarr( $meta_query, 'relation', 'AND' ) ) {
			return;
		}

		foreach ( $meta_query as $single_meta_query_key => $single_meta_query ) {

			if ( '=' !== toolset_getarr( $single_meta_query, 'compare', '=' ) ) {
				continue;
			}

			$parent_post_type = $this->parse_legacy_meta_key( toolset_getarr( $single_meta_query, 'key' ) );
			if ( null === $parent_post_type ) {
				continue;
			}

			$parent_post_id = (int) toolset_getarr( $single_meta_query, 'value' );

			$was_transformed = $this->try_transforming_legacy_meta_query( $query, $parent_post_type, $parent_post_id );

			if ( ! $was_transformed ) {
				continue;
			}

			unset( $query->query_vars['meta_query'][ $single_meta_query_key ] );
		}
	}


	/**
	 * Extract the parent post type from the legacy relationship postmeta key.
	 *
	 * @param string|string[] $meta_key
	 *
	 * @return string|null Parent post type slug or null if the meta_key stands for something else.
	 */
	private function parse_legacy_meta_key( $meta_key ) {
		if ( ! is_string( $meta_key ) ) {
			// We don't support multiple meta_key values for a legacy post relationship query.
			return null;
		}

		$matches = array();
		preg_match( '/^_wpcf_belongs_([a-z0-9_-]+)_id$/', $meta_key, $matches );

		if ( empty( $matches ) || count( $matches ) < 2 ) {
			return null;
		}

		return $matches[1];
	}


}
