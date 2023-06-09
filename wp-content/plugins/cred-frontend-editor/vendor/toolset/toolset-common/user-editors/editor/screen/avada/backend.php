<?php
/**
 * Backend Editor class for Fusion Builder (Avada).
 *
 * Handles all the functionality needed to allow for Fusion Builder (Avada) to work with Content Template editing on the backend.
 *
 * @since 2.5.9
 */
class Toolset_User_Editors_Editor_Screen_Avada_Backend
	extends Toolset_User_Editors_Editor_Screen_Abstract {

	public function initialize() {
		parent::initialize();

		add_action( 'init', array( $this, 'register_assets' ), 50 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_assets' ), 50 );

		add_filter( 'toolset_filter_toolset_registered_user_editors', array( $this, 'register_user_editor' ) );
		add_filter( 'wpv_filter_wpv_layout_template_extra_attributes', array( $this, 'layout_template_attribute' ), 10, 2 );

		add_action( 'wpv_action_wpv_ct_inline_user_editor_buttons', array( $this, 'register_inline_editor_action_buttons' ) );

		add_action( 'toolset_set_layout_template_user_editor_avada', array( $this, 'update_fusion_builder_post_meta' ) );
		add_action( 'toolset_set_layout_template_user_editor_basic', array( $this, 'update_fusion_builder_post_meta' ) );

		// Registers the Avada editor as one of the allowed default user editors for Content Templates.
		add_filter( 'wpv_filter_wpv_default_user_editor', array( $this, 'register_avada_as_default_user_editor_option' ) );
    }

	public function is_active() {
		if ( ! $this->set_medium_as_post() ) {
			return false;
		}

		$this->action();

		return true;
	}

	private function action() {
		add_action( 'admin_enqueue_scripts', array( $this, 'action_enqueue_assets' ) );
		$this->medium->set_html_editor_backend( array( $this, 'html_output' ) );
		$this->medium->page_reload_after_backend_save();
	}

	public function register_assets() {

		$toolset_assets_manager = Toolset_Assets_Manager::get_instance();

		// Content Template own edit screen assets
		$toolset_assets_manager->register_style(
			'toolset-user-editors-avada-style',
			TOOLSET_COMMON_URL . '/user-editors/editor/screen/avada/backend.css',
			array(),
			TOOLSET_COMMON_VERSION
		);

		// Native post editor screen assets
		$toolset_assets_manager->register_script(
			'toolset-user-editors-avada-script',
			TOOLSET_COMMON_URL . '/user-editors/editor/screen/avada/backend_editor.js',
			array( 'jquery' ),
			TOOLSET_COMMON_VERSION,
			true
		);

		$toolset_assets_manager->register_style(
			'toolset-user-editors-avada-editor-style',
			TOOLSET_COMMON_URL . '/user-editors/editor/screen/avada/backend_editor.css',
			array(),
			TOOLSET_COMMON_VERSION
		);

		// Content Template as inline object assets
		$toolset_assets_manager->register_script(
			'toolset-user-editors-avada-layout-template-script',
			TOOLSET_COMMON_URL . '/user-editors/editor/screen/avada/backend_layout_template.js',
			array( 'jquery', 'views-layout-template-js', 'underscore' ),
			TOOLSET_COMMON_VERSION,
			true
		);

		$avada_layout_template_i18n = array(
			'template_editor_url' => admin_url( 'admin.php?page=ct-editor' ),
			'template_overlay' => array(
				'title' => sprintf( __( 'You created this template using Avada’s %1$s', 'wpv-views' ), $this->editor->get_name() ),
				'button' => sprintf( __( 'Edit with %1$s', 'wpv-views' ), $this->editor->get_name() ),
				'discard' => sprintf( __( 'Stop using %1$s for this Content Template', 'wpv-views' ), $this->editor->get_name() ),
			),
		);

		$toolset_assets_manager->localize_script(
			'toolset-user-editors-avada-layout-template-script',
			'toolset_user_editors_avada_layout_template_i18n',
			$avada_layout_template_i18n
		);
	}

	public function admin_enqueue_assets() {
		if ( $this->is_views_or_wpa_edit_page() ) {
			do_action( 'toolset_enqueue_scripts', array( 'toolset-user-editors-avada-layout-template-script' ) );
		}
	}

	public function action_enqueue_assets() {
		do_action( 'toolset_enqueue_styles', array( 'toolset-user-editors-avada-style' ) );
	}

	private function set_medium_as_post() {
		$medium_id  = $this->medium->get_id();

		if ( ! $medium_id ) {
			return false;
		}

		$medium_post_object = get_post( $medium_id );
		if ( null === $medium_post_object ) {
			return false;
		}

		$this->post = $medium_post_object;

		return true;
	}

	public function register_user_editor( $editors ) {
		$editors[ $this->editor->get_id() ] = $this->editor->get_name();
		return $editors;
	}

	/**
	 * Content Template editor output.
	 *
	 * Displays the Native Editor message and button to fire it up.
	 *
	 * @since 2.5.0
	 */
	public function html_output() {

		if ( ! isset( $_GET['ct_id'] ) ) {
			return 'No valid content template id';
		}

		ob_start();
		include_once( dirname( __FILE__ ) . '/backend.phtml' );
		$output = ob_get_contents();
		ob_end_clean();

		$admin_url = admin_url( 'admin.php?page=ct-editor&ct_id=' . esc_attr( $_GET['ct_id'] ) );
		$output .= '<p>'
				   . sprintf(
					   __( '%1$sStop using %2$s for this Content Template%3$s', 'wpv-views' ),
					   '<a href="' . esc_url( $admin_url ) . '&ct_editor_choice=basic">',
					   $this->editor->get_name(),
					   '</a>'
				   )
				   . '</p>';

		return $output;
	}

	public function register_inline_editor_action_buttons( $content_template ) {
		$content_template_has_avada = ( get_post_meta( $content_template->ID, '_toolset_user_editors_editor_choice', true ) === Toolset_User_Editors_Editor_Avada::AVADA_SCREEN_ID );
		?>
		<button
			class="button button-secondary toolset-ct-button-logo js-wpv-ct-apply-user-editor js-wpv-ct-apply-user-editor-<?php echo esc_attr( $this->editor->get_id() ); ?> <?php echo $this->editor->get_logo_class(); ?>"
			data-editor="<?php echo esc_attr( $this->editor->get_id() ); ?>"
            title="<?php echo __( 'Edit with', 'wpv-views' ) . ' ' . $this->editor->get_name() ?>"
			<?php disabled( $content_template_has_avada ); ?>
		>
			<?php echo esc_html( $this->editor->get_name() ); ?>
		</button>
		<?php
	}

	/**
	 * Set the builder used by a Content Template, if any.
	 *
	 * On a Content Template used inside a View or WPA loop output, we set which builder it is using
	 * so we can link to the CT edit page with the right builder instantiated.
	 *
	 * @param array   $attributes
	 * @param WP_POST $content_template
	 *
	 * @return array
	 *
	 * @since 2.5.0
	 */
	public function layout_template_attribute( $attributes, $content_template ) {
		$content_template_has_avada = ( get_post_meta( $content_template->ID, '_toolset_user_editors_editor_choice', true ) === Toolset_User_Editors_Editor_Avada::AVADA_SCREEN_ID );
		if ( $content_template_has_avada ) {
			$attributes['builder'] = $this->editor->get_id();
		}
		return $attributes;
	}

	public function update_fusion_builder_post_meta() {
		if (
			isset( $_POST['ct_id'] )
			&& $_POST['ct_id']
		) {
			do_action( 'toolset_update_fusion_builder_post_meta', $_POST['ct_id'], 'editor' );
		}
	}

	/**
	 * Registers the Avada editor as one of the allowed default user editors for Content Templates.
	 *
	 * @param array $default_editors
	 *
	 * @return array
	 */
	public function register_avada_as_default_user_editor_option( $default_editors ) {
		array_push( $default_editors, Toolset_User_Editors_Editor_Avada::AVADA_SCREEN_ID );
		return $default_editors;
	}
}
