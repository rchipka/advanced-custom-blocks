<?php
/**
* @wordpress-plugin
* Plugin Name: Advanced Custom Blocks
* Plugin URI: https://github.com/rchipka/advanced-custom-blocks
* Description: ACF for Gutenberg blocks
* Version: 2.1.9
* Author: Robbie Chipka
* Author URI: https://github.com/rchipka`
* GitHub Plugin URI: https://github.com/rchipka/advanced-custom-blocks
*/

global $acb_block;
global $acb_current_field;

include_once('acf-block-field.php');

add_action('acf/include_field_types', function ($version) {
  if ( $version != 5 ) {
    return;
  }

  include_once('acf-block-field.php');
}, 10, 1);

function acb_current_block($key = null) {
  global $acb_block;

  if (!$key) {
    if (is_array($acb_block)) {
      return $acb_block;
    }

    return [];
  }
  
  if ($key === 'name') {
    $key = 'block_name';
  }

  return acb_current_block()[$key];
}

add_filter('acf/location/rule_types', function ( $choices ) {
    $choices['Gutenberg']['block_name'] = 'Block Name';
    return $choices;
}, 10, 1);

add_filter('acf/location/rule_values/block_name', function ( $choices, $data ) {
    return
      '<input type="text" data-ui="0" data-ajax="0" data-multiple="0" data-allow_null="0" ' .
        'id="acf_field_group-location-' . $data['group'] . '-' . $data['id'] . '-value" ' .
        'name="acf_field_group[location][' . $data['group'] . '][' . $data['id'] . '][value]" ' .
        'value="' . $data['value'] . '" />';
}, 10, 2);

add_filter('acf/location/rule_match/block_name', function ( $match, $rule, $options ) {
  if (empty($rule['value'])) {
    return true;
  }

  return ($rule['operator'] === '==') === (acb_current_block('name') == $rule['value']);
}, 10, 3);

add_filter('acf/location/screen', function ($screen, $field_group) {
  if (!isset($field_group['block_name']) || !$field_group['block_name']) {
    return $screen;
  }

  if (!isset($screen['post_id']) || !$screen['post_id']) {
    $screen['post_id'] = $_REQUEST['post'] ?: $_REQUEST['post_id'] ?: $_REQUEST['attributes']['post_id'];
  }

  return $screen;
}, 1, 2);

add_filter('get_post_metadata', function ($orig_value, $post_id, $meta_key, $single) {
  $block_meta = acb_current_block('block_meta');

  if (!$block_meta || !isset($block_meta[$meta_key])) {
    return $orig_value;
  }

  $meta_value = $block_meta[$meta_key];

  if ($single && is_array($meta_value)) {
    $meta_value = $meta_value[0];
  }

  if (!$single && !is_array($meta_value)) {
    $meta_value = [$meta_value];
  }

  // $type = 'single';

  // if (!$single) {
  //   $type = 'array';
  // }

  // error_log('Block #' . acb_current_block('block_id') . ' - overriding ' . $type . ' value for ' . json_encode($meta_key) . ' from ' .  json_encode($orig_value) . ' to '  . json_encode($meta_value));

  return $meta_value;
}, 0, 4);

add_action('acf/render_field_group_settings', function ($field_group) {
  acf_render_field_wrap(array(
    'label'     => 'Block Name',
    'instructions'  => '',
    'type'      => 'text',
    'name'      => 'block_name',
    'prefix'    => 'acf_field_group',
    'value'     => $field_group['block_name'],
  ));

  acf_render_field_wrap(array(
    'label'     => 'Block Icon',
    'instructions'  => '',
    'type'      => 'text',
    'name'      => 'block_icon',
    'placeholder' => 'Dashicons class name',
    'prefix'    => 'acf_field_group',
    'value'     => $field_group['block_icon'],
  ));

  acf_render_field_wrap(array(
    'label'     => 'Block Category',
    'instructions'  => '',
    'type'      => 'text',
    'name'      => 'block_category',
    'prefix'    => 'acf_field_group',
    'placeholder' => 'Widgets',
    'value'     => $field_group['block_category'],
  ));
}, 10, 1);

function my_plugin_block_categories( $categories, $post ) {
  if ( $post->post_type !== 'post' ) {
    return $categories;
  }
  return array_merge(
    $categories,
    array(
      array(
        'slug' => 'my-category',
        'title' => __( 'My category', 'my-plugin' ),
      ),
    )
  );
}

add_filter( 'block_categories', function ($block_categories, $post) {
  $block_category_slugs = wp_list_pluck($block_categories, 'slug');

  setup_postdata($post);

  foreach (wp_list_pluck(acf_gb_get_block_field_groups(), 'block_category') as $category) {
    $slug = sanitize_title($category);

    if (!in_array($slug, $block_category_slugs)) {
      $block_category_slugs[] = $slug;
      $block_categories[] = [
        'slug' => $slug,
        'title' => $category
      ];
    }
  }

  wp_reset_postdata();

  return $block_categories;
}, 5, 2 );


add_action( 'init', function () {
  register_post_type('acf-child-block', [
    'label' => 'ACF Block',
    'labels' => [],
    'public' => true,
    'show_in_menu' => false,
    'capability_type' => 'page',
    'supports' => array( 'title', 'editor', 'comments', 'thumbnail', 'custom-fields' ),
  ]);

  if (!function_exists('register_block_type')) {
    return;
  }

  foreach (acf_gb_get_block_field_groups() as $group) {
    register_block_type( 'acf/' . $group['block_name'], array(
      'attributes'      => array(
        'post_id' => array(
          'type'    => 'number',
          'default' => 0,
        ),
        'block_id' => array(
          'type'    => 'string',
          'default' => '',
        ),
        'block_name' => array(
          'type'    => 'string',
          'default' => $group['block_name'],
        ),
        'acf_fields' => array(
          'type'    => 'object',
          'default' => [],
        ),
        'acf_field_group' => array(
          'type'    => 'number',
          'default' => 0,
        ),
      ),
      'render_callback' => function ($attributes) {
        global $post;
        global $acb_block;

        $output = '';

        if ($post instanceof WP_Post && $post->ID) {
          $post_id = $attributes['post_id'] = $post->ID;
        } else if ($attributes['post_id']) {
          setup_postdata($post = get_post($attributes['post_id']));
        }

        $cache_active = acf_is_cache_active();

        if ($cache_active) {
          acf_disable_cache();
        }

        $attributes['block_meta'] = get_post_meta($post_id, '__block_' . $attributes['block_id'], true);

        if (!is_array($attributes['block_meta'])) {
          $attributes['block_meta'] = [];
        }

        if (isset($_REQUEST['attributes']) && isset($_REQUEST['attributes']['acf_fields'])) {
          $post_id = $_REQUEST['attributes']['post_id'];
          $block_id = $_REQUEST['attributes']['block_id'];
          $acf_fields = $attributes['acf_fields'] = $_REQUEST['attributes']['acf_fields'];

          $fields = array_map(function ($field_key) {
            return get_field_object($field_key);
          }, array_keys($acf_fields));

          $filter_keys = [];

          foreach ($fields as $field) {
            if (!$field['name']) {
              continue;
            }

            $filter_keys[] = '_' . $field['name'];
            $filter_keys[] = $field['name'];
          }

          acf_save_post( $post_id, $acf_fields );

          if (sizeof($filter_keys) > 0)  {
            $attributes['block_meta'] = [];
            $regex = '/^(' . implode($filter_keys, '|') . ')/';

            foreach (get_post_meta($post_id) as $meta_key => $meta_values) {
              if (!preg_match($regex, $meta_key)) {
                continue;
              }

              $attributes['block_meta'][$meta_key] = $meta_values[0];
            }
          }

          // foreach ($fields as $field) {
          //   $meta_value = $attributes['block_meta'][$field_name];
          //   add_post_meta($post_id, $field['name'], $meta_value, false);
          // }

          update_post_meta($post_id, '__block_' . $block_id, $attributes['block_meta']);
        }

        $acb_block = $attributes;

        if ($_GET['context'] === 'edit') {
          ob_start();
          $fields = acf_get_fields($attributes['acf_field_group']);
          acf_render_fields($fields, $attributes['post_id']);
          $output .= trim(ob_get_contents());
          ob_end_clean();

          if (!$output) {
            $output = '<div></div>';
          }
        } else {
          ob_start();

          do_action('acf/before_render_block', $attributes);
          do_action('acf/before_render_block/name=' . $attributes['block_name'], $attributes);

          do_action('acf/render_block', $attributes);
          do_action('acf/render_block/name=' . $attributes['block_name'], $attributes);

          $file_name = $attributes['block_name'] . '.php';

          foreach (['/blocks/acf/' . $file_name, '/blocks/acf-' . $file_name] as $path) {
            if (file_exists(get_stylesheet_directory() . $path)) {
              include(get_stylesheet_directory() . $path);
            }
          }

          do_action('acf/after_render_block', $attributes);
          do_action('acf/after_render_block/name=' . $attributes['block_name'], $attributes);
          
          $output .= ob_get_contents();
          ob_end_clean();
        }

        reset_rows();
        wp_reset_postdata();
        $acb_block = null;

        if ($cache_active) {
          acf_enable_cache();
        }
        
        return $output;
      },
    ) );
  }
});

function acf_gb_get_block_field_groups() {
  global $acb_block;

  $groups = [];

  if (isset($_REQUEST['post']) && $_REQUEST['post']) {
    setup_postdata($post = get_post($_REQUEST['post']));
  }

  foreach (acf_get_field_groups() as $group) {
    $acb_block = [
      'block_name' => ($is_block = (isset($group['block_name']) ?: $group['block_name'])),
    ];

    if ($is_block) {
      if (!isset($group['block_category']) || !$group['block_category']) {
        $group['block_category'] = 'widgets';
      }

      if (isset($group['block_category'])) {
        $group['block_category_slug'] = sanitize_title($group['block_category']);
      }

      if (acf_get_field_group_visibility($group)) {
        $groups[] = $group;
      }
    }

    $acb_block = null;
  }

  if ($_REQUEST['post']) {
    wp_reset_postdata();
  }

  return $groups;
}

add_action('admin_notices', function () {
  ?>
<script>
(function ($) {
  if (!window.wp || !window.wp.blocks || !window.wp.editor) {
    return;
  }

  var wp = window.wp,
      groupElements = {},
      fieldGroups = {},
      fieldGroupForms = {},
      field_groups = <?php echo json_encode(acf_gb_get_block_field_groups()); ?>;

  console.log('ACF Field Groups:', field_groups);

  function loadACF(callback) {
    $.ajax({
      url: ajaxurl,
      method: 'POST',
      data: $.param({
        action: 'gutenberg_match_acf',
        post_id: <?php echo json_encode(get_the_ID()); ?>,
      }),
      success: function (field_groups) {
        callback(field_groups.sort(function (a, b) {
          return a.menu_order - b.menu_order;
        }), true);
      }
    });
  }

  field_groups.forEach(function (group) {
    var slug = 'acf/' + group.block_name;

    console.log('Register block', slug, group);

    wp.blocks.registerBlockType(slug, {
      title: group.title,
      description: group.description,
      icon: group.block_icon || 'feedback',
      category: group.block_category_slug || 'widgets',
      supports: {
        html: false,
      },
      getEditWrapperProps: function( attributes ) {
        return attributes;
      },
      edit: function (block) {
        // console.log('edit', block);
        var el = wp.element.createElement;

        block.attributes.post_id = <?php echo json_encode(intval(get_the_ID())); ?>;
        var block_id = block.attributes.block_id = block.attributes.block_id || block.clientId;

        var remote = el('form', {
          className: 'acf-block-group-content',
          'data-block-id': block_id,
        }, [
          el(wp.components.ServerSideRender, {
            block: slug,
            attributes: {
              acf_fields: block.attributes.acf_fields,
              acf_field_group: group.ID,
              post_id: <?php echo json_encode(intval(get_the_ID())); ?>,
              block_id: block_id,
            },
          })
        ]);

        var children = [];

        if (group.style === 'default') {
          children.push(el('div', { className: 'acf-block-group-heading' }, [
            el('span', {
              className: 'dashicons dashicons-' + (group.block_icon || 'feedback'),
            }),
            ' ',
            group.title
          ]));
        }

        var selector = 'form[data-block-id="' + block_id + '"]';

        if ($(selector).length < 1) {
          $(document).on('acb_save_fields', function () {
            var tryUpdate = function () {
              if (block.isSelected || $(selector).is(':hover')) {
                clearTimeout(block.updateTimeout);
                block.updateTimeout = setTimeout(tryUpdate, 500);
                return;
              }

              block.setAttributes({
                acf_fields: acf.serialize($(selector))['acf'],
              });
            };

            setTimeout(tryUpdate, 250);
          });
        }
        // setTimeout(function () {
        //   acf.do_action('ready', $('[data-block-id="' + block_id + '"]'));
        // }, 500);

        children.push(remote);

        return el('div', {
          className: 'acf-block-group-wrapper',
        }, children);
      },
      save: function (block) {
        // console.log('SAVE', block);

        return null;
      },
    })
  });

  $(document).on('change', 'form[data-block-id]', function () {
    $(document).trigger('acb_save_fields');
  });

  wp.apiFetch.use(function (options, next) { 
    if (options.path && /block-renderer\/acf/.test(options.path)) {
      var res = next(options);

      $('[data-block-id]').each(function () {
        $(this).css({ 'height': $(this).height() + 'px', 'overflow': 'hidden' });
      });

      res.then(function () {
        setTimeout(function () {
          $('[data-block-id]').each(function () {
            var self = this;

            acf.do_action('append', $(self));

            setTimeout(function () {
              $(self).css({ 'height': 'auto', 'overflow': 'auto' });
            }, 500);
          });
        }, 500);
      });

      return res;
    }

    // Publish: method === PUT
    // Autosave: method === POST 

    if ((options.method === 'PUT' || options.method === 'POST') && options.data && options.data.content) {
      $(document).trigger('acb_save_fields');
      
      // return new Promise(function (resolve, reject) {
      //   var interval = setInterval(function () {
      //     if ($('#editor .components-placeholder').length < 1) {
      //       doRequest();
      //     }
      //   }, 150);

      //   var doRequest = function () {
      //     clearInterval(interval);
      //     next(options).then(resolve).catch(reject);
      //   };

      //   setTimeout(doRequest, 1500);
      // });
      

      // return next(options);
    }

    if (options.method !== 'POST' || !(options.body instanceof FormData)) {
      return next(options);
    }

    $('.acf-block-group-content').each(function () {
      var form = this;

      // var data = $.param(acf.serialize($(this)));
      // console.log(data);

      (new FormData(this)).forEach(function (val, key) {
        // var val = data[key];
        console.log('Saving ACF field', key, val);

        options.body.append(key, val);

        key = key.replace(/^acf\[/, 'acf_blocks[' + $(form).data('block-id') + '][');
        console.log('Saving ACF field', key, val);
        options.body.append(key, val);
      });
    });

    return next(options);
  });
})(jQuery);
</script>
<style>
.acf-block-group-wrapper {
  overflow: auto;
}
.acf-block-group-heading {
  background-color: #EEE;
  padding: 0.25em 0.5em;
}
.acf-block-group-heading > .dashicons {
  vertical-align: text-top;
}
.acf-block-group-content {
}

.acf-block-group-content .acf-field-group {
  margin-top: 0px;
}

.acf-expand-details + .acf-expand-details {
  display: none;
}
</style>
<?php
}, 0);
