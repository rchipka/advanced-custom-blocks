<?php

// exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;


// check if class already exists
if( !class_exists('ACB_acf_field_block') ) :

class ACB_acf_field_block extends acf_field {
  
  
  /*
  *  __construct
  *
  *  This function will setup the field type data
  *
  *  @type  function
  *  @date  5/03/2014
  *  @since 5.0.0
  *
  *  @param n/a
  *  @return  n/a
  */
  
  function __construct( $settings ) {
    
    /*
    *  name (string) Single word, no spaces. Underscores allowed
    */
    
    $this->name = 'block';
    
    
    /*
    *  label (string) Multiple words, can include spaces, visible when selecting a field type
    */
    
    $this->label = __('Block', 'TEXTDOMAIN');
    
    
    /*
    *  category (string) basic | content | choice | relational | jquery | layout | CUSTOM GROUP NAME
    */
    
    $this->category = 'layout';
    
    
    /*
    *  defaults (array) Array of default settings which are merged into the field object. These are used later in settings
    */
    
    $this->defaults = array(
      // 'font_size' => 14,
    );
    
    
    /*
    *  l10n (array) Array of strings that are used in JavaScript. This allows JS strings to be translated in PHP and loaded via:
    *  var message = acf._e('block', 'error');
    */
    
    $this->l10n = array(
      'error' => __('Error! Please enter a higher value', 'TEXTDOMAIN'),
    );
    
    
    /*
    *  settings (array) Store plugin settings (url, path, version) as a reference for later use with assets
    */
    
    $this->settings = $settings;
    
    
    // do not delete!
      parent::__construct();
      
  }
  
  
  /*
  *  render_field_settings()
  *
  *  Create extra settings for your field. These are visible when editing a field
  *
  *  @type  action
  *  @since 3.6
  *  @date  23/01/13
  *
  *  @param $field (array) the $field being edited
  *  @return  n/a
  */
  
  function render_field_settings( $field ) {
    
    /*
    *  acf_render_field_setting
    *
    *  This function will create a setting for your field. Simply pass the $field parameter and an array of field settings.
    *  The array of settings does not require a `value` or `prefix`; These settings are found from the $field array.
    *
    *  More than one setting can be added by copy/paste the above code.
    *  Please note that you must also have a matching $defaults value for the field name (font_size)
    */
    
    // acf_render_field_setting( $field, array(
    //   'label'     => __('Font Size','TEXTDOMAIN'),
    //   'instructions'  => __('Customise the input font size','TEXTDOMAIN'),
    //   'type'      => 'number',
    //   'name'      => 'font_size',
    //   'prepend'   => 'px',
    // ));

  }
  
  
  
  /*
  *  render_field()
  *
  *  Create the HTML interface for your field
  *
  *  @param $field (array) the $field being rendered
  *
  *  @type  action
  *  @since 3.6
  *  @date  23/01/13
  *
  *  @param $field (array) the $field being edited
  *  @return  n/a
  */
  
  function render_field( $field ) {
    
    
    /*
    *  Review the data of $field.
    *  This will show what data is available
    */
    
    $block_id = acb_current_block('block_id');
    $parent_post_id = acb_current_block('post_id');

    if (!$block_id) {
      echo '<pre>';
        print_r( $field );
      echo '</pre>';

      return;
    }

    $field_id = $field['id'];
    $post_name = implode('-', ['acf-child-block', $parent_post_id, $block_id, $field_id]);
      echo '<pre>';
        print_r( $post_name );
        print_r( $field );
      echo '</pre>';

    if (!isset($field['value']) || !$field['value'] || !(get_post($field['value']))) {
      $field['value'] = intval(wp_insert_post([
        'post_type' => 'acf-child-block',
        'post_parent' => $parent_post_id,
        'post_name' => $post_name,
      ]));

      update_post_meta($parent_post_id, $field_id, $field['value']);
    }

    $post = get_post($field['value']);
    $editor_settings = acb_get_editor_settings($post);
    $post_name .= '-' . rand(0, 99999);
    ?>
    <input type="hidden" name="<?php echo esc_attr($field['name']) ?>" value="<?php echo esc_attr($field['value']); ?>" />
    <div class="acf-child-block-preview" onclick="$(document.body).append('<div id=\'<?php echo $post_name; ?>\'></div>'); wp.editPost.initializeEditor( '<?php echo $post_name; ?>', 'acf-child-block', <?php echo $field['value']; ?>, JSON.parse(decodeURIComponent('<?php echo urlencode(json_encode($editor_settings)); ?>')), null );">
      content
      <?php echo apply_filters('the_content', $post->post_content); ?>
    </div>
    <?php
  }
  
    
  /*
  *  input_admin_enqueue_scripts()
  *
  *  This action is called in the admin_enqueue_scripts action on the edit screen where your field is created.
  *  Use this action to add CSS + JavaScript to assist your render_field() action.
  *
  *  @type  action (admin_enqueue_scripts)
  *  @since 3.6
  *  @date  23/01/13
  *
  *  @param n/a
  *  @return  n/a
  */

  /*
  
  function input_admin_enqueue_scripts() {
    
    // vars
    $url = $this->settings['url'];
    $version = $this->settings['version'];
    
    
    // register & include JS
    wp_register_script('TEXTDOMAIN', "{$url}assets/js/input.js", array('acf-input'), $version);
    wp_enqueue_script('TEXTDOMAIN');
    
    
    // register & include CSS
    wp_register_style('TEXTDOMAIN', "{$url}assets/css/input.css", array('acf-input'), $version);
    wp_enqueue_style('TEXTDOMAIN');
    
  }
  
  */
  
  
  /*
  *  input_admin_head()
  *
  *  This action is called in the admin_head action on the edit screen where your field is created.
  *  Use this action to add CSS and JavaScript to assist your render_field() action.
  *
  *  @type  action (admin_head)
  *  @since 3.6
  *  @date  23/01/13
  *
  *  @param n/a
  *  @return  n/a
  */

  /*
    
  function input_admin_head() {
  
    
    
  }
  
  */
  
  
  /*
    *  input_form_data()
    *
    *  This function is called once on the 'input' page between the head and footer
    *  There are 2 situations where ACF did not load during the 'acf/input_admin_enqueue_scripts' and 
    *  'acf/input_admin_head' actions because ACF did not know it was going to be used. These situations are
    *  seen on comments / user edit forms on the front end. This function will always be called, and includes
    *  $args that related to the current screen such as $args['post_id']
    *
    *  @type  function
    *  @date  6/03/2014
    *  @since 5.0.0
    *
    *  @param $args (array)
    *  @return  n/a
    */
    
    /*
    
    function input_form_data( $args ) {
      
    
  
    }
    
    */
  
  
  /*
  *  input_admin_footer()
  *
  *  This action is called in the admin_footer action on the edit screen where your field is created.
  *  Use this action to add CSS and JavaScript to assist your render_field() action.
  *
  *  @type  action (admin_footer)
  *  @since 3.6
  *  @date  23/01/13
  *
  *  @param n/a
  *  @return  n/a
  */

  /*
    
  function input_admin_footer() {
  
    
    
  }
  
  */
  
  
  /*
  *  field_group_admin_enqueue_scripts()
  *
  *  This action is called in the admin_enqueue_scripts action on the edit screen where your field is edited.
  *  Use this action to add CSS + JavaScript to assist your render_field_options() action.
  *
  *  @type  action (admin_enqueue_scripts)
  *  @since 3.6
  *  @date  23/01/13
  *
  *  @param n/a
  *  @return  n/a
  */

  /*
  
  function field_group_admin_enqueue_scripts() {
    
  }
  
  */

  
  /*
  *  field_group_admin_head()
  *
  *  This action is called in the admin_head action on the edit screen where your field is edited.
  *  Use this action to add CSS and JavaScript to assist your render_field_options() action.
  *
  *  @type  action (admin_head)
  *  @since 3.6
  *  @date  23/01/13
  *
  *  @param n/a
  *  @return  n/a
  */

  /*
  
  function field_group_admin_head() {
  
  }
  
  */


  /*
  *  load_value()
  *
  *  This filter is applied to the $value after it is loaded from the db
  *
  *  @type  filter
  *  @since 3.6
  *  @date  23/01/13
  *
  *  @param $value (mixed) the value found in the database
  *  @param $post_id (mixed) the $post_id from which the value was loaded
  *  @param $field (array) the field array holding all the field options
  *  @return  $value
  */
  
  /*
  
  function load_value( $value, $post_id, $field ) {
    
    return $value;
    
  }
  
  */
  
  
  /*
  *  update_value()
  *
  *  This filter is applied to the $value before it is saved in the db
  *
  *  @type  filter
  *  @since 3.6
  *  @date  23/01/13
  *
  *  @param $value (mixed) the value found in the database
  *  @param $post_id (mixed) the $post_id from which the value was loaded
  *  @param $field (array) the field array holding all the field options
  *  @return  $value
  */
  
  /*
  
  function update_value( $value, $post_id, $field ) {
    
    return $value;
    
  }
  
  */
  
  
  /*
  *  format_value()
  *
  *  This filter is appied to the $value after it is loaded from the db and before it is returned to the template
  *
  *  @type  filter
  *  @since 3.6
  *  @date  23/01/13
  *
  *  @param $value (mixed) the value which was loaded from the database
  *  @param $post_id (mixed) the $post_id from which the value was loaded
  *  @param $field (array) the field array holding all the field options
  *
  *  @return  $value (mixed) the modified value
  */
    
  /*
  
  function format_value( $value, $post_id, $field ) {
    
    // bail early if no value
    if( empty($value) ) {
    
      return $value;
      
    }
    
    
    // apply setting
    if( $field['font_size'] > 12 ) { 
      
      // format the value
      // $value = 'something';
    
    }
    
    
    // return
    return $value;
  }
  
  */
  
  
  /*
  *  validate_value()
  *
  *  This filter is used to perform validation on the value prior to saving.
  *  All values are validated regardless of the field's required setting. This allows you to validate and return
  *  messages to the user if the value is not correct
  *
  *  @type  filter
  *  @date  11/02/2014
  *  @since 5.0.0
  *
  *  @param $valid (boolean) validation status based on the value and the field's required setting
  *  @param $value (mixed) the $_POST value
  *  @param $field (array) the field array holding all the field options
  *  @param $input (string) the corresponding input name for $_POST value
  *  @return  $valid
  */
  
  /*
  
  function validate_value( $valid, $value, $field, $input ){
    
    // Basic usage
    if( $value < $field['custom_minimum_setting'] )
    {
      $valid = false;
    }
    
    
    // Advanced usage
    if( $value < $field['custom_minimum_setting'] )
    {
      $valid = __('The value is too little!','TEXTDOMAIN'),
    }
    
    
    // return
    return $valid;
    
  }
  
  */
  
  
  /*
  *  delete_value()
  *
  *  This action is fired after a value has been deleted from the db.
  *  Please note that saving a blank value is treated as an update, not a delete
  *
  *  @type  action
  *  @date  6/03/2014
  *  @since 5.0.0
  *
  *  @param $post_id (mixed) the $post_id from which the value was deleted
  *  @param $key (string) the $meta_key which the value was deleted
  *  @return  n/a
  */
  
  /*
  
  function delete_value( $post_id, $key ) {
    
    
    
  }
  
  */
  
  
  /*
  *  load_field()
  *
  *  This filter is applied to the $field after it is loaded from the database
  *
  *  @type  filter
  *  @date  23/01/2013
  *  @since 3.6.0 
  *
  *  @param $field (array) the field array holding all the field options
  *  @return  $field
  */
  
  /*
  
  function load_field( $field ) {
    
    return $field;
    
  } 
  
  */
  
  
  /*
  *  update_field()
  *
  *  This filter is applied to the $field before it is saved to the database
  *
  *  @type  filter
  *  @date  23/01/2013
  *  @since 3.6.0
  *
  *  @param $field (array) the field array holding all the field options
  *  @return  $field
  */
  
  /*
  
  function update_field( $field ) {
    
    return $field;
    
  } 
  
  */
  
  
  /*
  *  delete_field()
  *
  *  This action is fired after a field is deleted from the database
  *
  *  @type  action
  *  @date  11/02/2014
  *  @since 5.0.0
  *
  *  @param $field (array) the field array holding all the field options
  *  @return  n/a
  */
  
  /*
  
  function delete_field( $field ) {
    
    
    
  } 
  
  */
  
  
}

function acb_get_editor_settings($post) {

    $available_templates = wp_get_theme()->get_page_templates( get_post( $post->ID ) );
    $available_templates = ! empty( $available_templates ) ? array_merge(
      array(
        '' => apply_filters( 'default_page_template_title', __( 'Default template', 'gutenberg' ), 'rest-api' ),
      ),
      $available_templates
    ) : $available_templates;

    $gutenberg_theme_support = get_theme_support( 'gutenberg' );
    $align_wide              = get_theme_support( 'align-wide' );
    $color_palette           = current( (array) get_theme_support( 'editor-color-palette' ) );
    $font_sizes              = current( (array) get_theme_support( 'editor-font-sizes' ) );
    $max_upload_size = wp_max_upload_size();
    if ( ! $max_upload_size ) {
      $max_upload_size = 0;
    }
    global $editor_styles;
    $styles = array(
      array(
        'css' => file_get_contents(
          gutenberg_dir_path() . 'build/editor/editor-styles.css'
        ),
      ),
    );
    if ( $editor_styles && current_theme_supports( 'editor-styles' ) ) {
      foreach ( $editor_styles as $style ) {
        if ( filter_var( $style, FILTER_VALIDATE_URL ) ) {
          $styles[] = array(
            'css' => file_get_contents( $style ),
          );
        } else {
          $file     = get_theme_file_path( $style );
          $styles[] = array(
            'css'     => file_get_contents( get_theme_file_path( $style ) ),
            'baseURL' => get_theme_file_uri( $style ),
          );
        }
      }
    }

    // Lock settings.
    $user_id = wp_check_post_lock( $post->ID );
    if ( $user_id ) {
      /**
       * Filters whether to show the post locked dialog.
       *
       * Returning a falsey value to the filter will short-circuit displaying the dialog.
       *
       * @since 3.6.0
       *
       * @param bool         $display Whether to display the dialog. Default true.
       * @param WP_Post      $post    Post object.
       * @param WP_User|bool $user    The user id currently editing the post.
       */
      if ( apply_filters( 'show_post_locked_dialog', true, $post, $user_id ) ) {
        $locked = true;
      }

      $user_details = null;
      if ( $locked ) {
        $user         = get_userdata( $user_id );
        $user_details = array(
          'name' => $user->display_name,
        );
        $avatar       = get_avatar( $user_id, 64 );
        if ( $avatar ) {
          if ( preg_match( "|src='([^']+)'|", $avatar, $matches ) ) {
            $user_details['avatar'] = $matches[1];
          }
        }
      }

      $lock_details = array(
        'isLocked' => $locked,
        'user'     => $user_details,
      );
    } else {

      // Lock the post.
      $active_post_lock = wp_set_post_lock( $post->ID );
      $lock_details     = array(
        'isLocked'       => false,
        'activePostLock' => esc_attr( implode( ':', $active_post_lock ) ),
      );
    }

    $editor_settings = array(
      'alignWide'              => $align_wide || ! empty( $gutenberg_theme_support[0]['wide-images'] ),
      'availableTemplates'     => $available_templates,
      'allowedBlockTypes'      => apply_filters( 'allowed_block_types', true, $post ),
      'disableCustomColors'    => get_theme_support( 'disable-custom-colors' ),
      'disableCustomFontSizes' => get_theme_support( 'disable-custom-font-sizes' ),
      'disablePostFormats'     => ! current_theme_supports( 'post-formats' ),
      'titlePlaceholder'       => apply_filters( 'enter_title_here', __( 'Add title', 'gutenberg' ), $post ),
      'bodyPlaceholder'        => apply_filters( 'write_your_story', __( 'Write your story', 'gutenberg' ), $post ),
      'isRTL'                  => is_rtl(),
      'autosaveInterval'       => 10,
      'maxUploadFileSize'      => $max_upload_size,
      'allowedMimeTypes'       => get_allowed_mime_types(),
      'styles'                 => $styles,
      'postLock'               => $lock_details,
      'postLockUtils'          => array(
        'nonce'       => wp_create_nonce( 'lock-post_' . $post->ID ),
        'unlockNonce' => wp_create_nonce( 'update-post_' . $post->ID ),
        'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
      ),
    );

    return $editor_settings;
}


// initialize
new ACB_acf_field_block( [] );


// class_exists check
endif;

?>
