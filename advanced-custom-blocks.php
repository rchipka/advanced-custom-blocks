<?php
/**
* @wordpress-plugin
* Plugin Name: Advanced Custom Blocks
* Plugin URI: https://github.com/rchipka/advanced-custom-blocks
* Description: ACF for Gutenberg blocks
* Version: 2.1.0
* Author: Robbie Chipka
* Author URI: https://github.com/rchipka
* GitHub Plugin URI: https://github.com/rchipka/advanced-custom-blocks
*/

global $acb_block;
global $acb_current_field;

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
  global $acb_block;

  if (empty($rule['value'])) {
    return true;
  }

  return ($rule['operator'] === '==') === (acb_current_block('name') == $rule['value']);
}, 10, 3);

add_filter('acf/location/screen', function ($screen, $field_group) {
  if (!$field_group['block_name']) {
    return $screen;
  }

  if (!$screen['post_id']) {
    $screen['post_id'] = $_REQUEST['post'] ?: $_REQUEST['post_id'] ?: $_REQUEST['attributes']['post_id'];
  }

  return $screen;
}, 1, 2);

add_action('save_post', function ($post_id) {
  if (!$_POST['acf_blocks']) {
    return;
  }

  // error_log(print_r($_POST['acf_blocks'], 1));

  
  foreach ($_POST['acf_blocks'] as $block_id => $fields) {
    foreach ($fields as $field_key => $meta_value) {
      $field = get_field_object($field_key);
      delete_post_meta($post_id, $field['name']);

      update_field($field_key, $meta_value, $post_id);
    }
  }

  foreach ($_POST['acf_blocks'] as $block_id => $fields) {
    foreach ($fields as $field_key => $meta_value) {
      $field = get_field_object($field_key);

      add_post_meta($post_id, $field['name'], $meta_value, false);
    }
  }
}, 10, 1);

add_filter('acf/load_field', function ($field) {
  global $acb_current_field;

  if (!acb_current_block('block_id') || !acb_current_block('post_id')) {
    return $field;
  }

  $value = acb_current_block('acf_fields');

  if ($value) {
    if (isset($value[$field['key']])) {
      $value = $value[$field['key']];
    }

    if (isset($field['sub_fields'])) {
      $acb_current_field = $field;
      $value = array_values($value);
    }

    $field['value'] = $value;
  }

  // error_log('load field ' . $field['name']. $field['key'] . '_' . $GLOBALS['ACF_BLOCK_ID'] . print_r($value, 1));

  return $field;
}, 10, 1);

// add_filter('acf/load_value', function ($value, $post_id, $field) {
//   global $acb_current_field;

//   if ($acb_current_field && isset($acb_current_field['value']) && $field['parent'] === $acb_current_field['ID']) {
//     // print_r($acb_current_field);
//     // return 'yes';
//     return $acb_current_field['value'][0][$field['key']];
//   }

//   return $value;
// }, 10, 3);

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
    'prefix'    => 'acf_field_group',
    'value'     => $field_group['block_icon'],
  ));
}, 10, 1);


add_action( 'init', function () {
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

        if ($attributes['post_id']) {
          setup_postdata($post = get_post($attributes['post_id']));
        }

        $cache_active = acf_is_cache_active();

        if ($cache_active) {
          acf_disable_cache();
        }

        if (isset($_REQUEST['attributes']) && isset($_REQUEST['attributes']['acf_fields'])) {
          $attributes['acf_fields'] = $_REQUEST['attributes']['acf_fields'];
        }

        $acb_block = $attributes;

        if ($_GET['context'] === 'edit') {
          ob_start();
          $fields = acf_get_fields($attributes['acf_field_group']);
          acf_render_fields($fields, $attributes['post_id']);
          $output .= ob_get_contents();
          ob_end_clean();
        } else {
          ob_start();

          do_action('acf/before_render_block', $attributes);
          do_action('acf/before_render_block/name=' . $attributes['block_name'], $attributes);

          do_action('acf/render_block', $attributes);
          do_action('acf/render_block/name=' . $attributes['block_name'], $attributes);

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

  if ($_REQUEST['post']) {
    setup_postdata($post = get_post($_REQUEST['post']));
  }

  foreach (acf_get_field_groups() as $group) {
    $acb_block = [
      'block_name' => ($is_block = ($group['block_name'])),
    ];

    if ($is_block) {

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

  var groupElements = {},
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
      category: 'widgets',
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
            block.setAttributes({
              acf_fields: acf.serialize($(selector))['acf'],
            });
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

  wp.apiFetch.use(function (options, next) { 
    if (options.path && /block-renderer\/acf/.test(options.path)) {
      var res = next(options);

      res.then(function () {
        setTimeout(function () {
          $('[data-block-id]').each(function () {
            acf.do_action('ready', $(this));
          });
        }, 500);
      });

      return res;
    }

    if ((options.method === 'PUT' || options.method === 'POST') && options.data && options.data.content) {
      $(document).trigger('acb_save_fields');

      return new Promise(function (resolve, reject) {
        var interval = setInterval(function () {
          if ($('#editor .components-placeholder').length < 1) {
            doRequest();
          }
        }, 150);

        var doRequest = function () {
          clearInterval(interval);
          next(options).then(resolve).catch(reject);
        };

        setTimeout(doRequest, 1500);
      });
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

        key = key.replace(/^acf/, 'acf_blocks[' + $(form).data('block-id') + ']');
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
</style>
<?
}, 0);
