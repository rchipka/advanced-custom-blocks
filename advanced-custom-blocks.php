<?php
/**
* @wordpress-plugin
* Plugin Name: Advanced Custom Blocks
* Plugin URI: https://github.com/rchipka/advanced-custom-blocks
* Description: ACF for Gutenberg blocks
* Version: 2.0.2
* Author: Robbie Chipka
* Author URI: https://github.com/rchipka
* GitHub Plugin URI: https://github.com/rchipka/advanced-custom-blocks
*/

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

  return ($rule['operator'] === '==') === ($GLOBALS['ACF_BLOCK_NAME'] == $rule['value']);
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
    foreach ($fields as $key => $value) {
      update_post_meta($post_id, $key . '_' . $block_id, $value);
    }
  }
}, 10, 1);

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

add_filter('acf/load_field', function ($field) {
  if (!$GLOBALS['ACF_BLOCK_ID'] || !$GLOBALS['ACF_POST_ID']) {
    return $field;
  }

  $value = get_post_meta($GLOBALS['ACF_POST_ID'], $field['key'] . '_' . $GLOBALS['ACF_BLOCK_ID'], true);

  if ($value) {
    $field['value'] = $value;
  }
  elseif ( $field['type'] == 'image' ) {
    $field['value'] = wp_get_attachment_image_src($value);
  }

  // error_log('load field ' . $field['name']. $field['key'] . '_' . $GLOBALS['ACF_BLOCK_ID'] . print_r($value, 1));

  return $field;
}, 10, 1);


// add_filter('acf/load_value', function ($value, $post_id, $field) {
//   error_log('load_field'. json_encode($field['name']));
//   if (!$GLOBALS['ACF_BLOCK_ID']) {
//     return $value;
//   }

//   error_log($post_id . $field['key'] . '_' . $GLOBALS['ACF_BLOCK_ID']);
//   return get_post_meta($post_id, $field['key'] . '_' . $GLOBALS['ACF_BLOCK_ID'], true) ?: $value;
// }, 10, 3);


add_action( 'init', function () {
  if (!function_exists('register_block_type')) {
    return;
  }

  foreach (acf_gb_get_block_field_groups() as $group) {
    register_block_type( 'acf/' . $group['block_name'], array(
      'attributes'      => array(
        'acf_fields' => array(
          'type'    => 'string',
          'default' => '',
        ),
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
        'acf_field_group' => array(
          'type'    => 'string',
          'default' => '',
        ),
      ),
      'render_callback' => function ($attributes) {
        global $post;

        $output = '';

        if ($attributes['post_id']) {
          setup_postdata($post = get_post($attributes['post_id']));
        }

        $cache_active = acf_is_cache_active();

        if ($cache_active) {
          acf_disable_cache();
        }

        $field_group = json_decode($attributes['acf_field_group'], true);

        $GLOBALS['ACF_POST_ID'] = $attributes['post_id'];
        $GLOBALS['ACF_BLOCK_ID'] = $attributes['block_id'];
        $GLOBALS['ACF_BLOCK_NAME'] = $attributes['block_name'];

        if ($_GET['context'] === 'edit') {
          ob_start();
          $fields = acf_get_fields($field_group);
          acf_render_fields($fields, $attributes['post_id']);
          $output .= ob_get_contents();
          ob_end_clean();
        } else {
          $output = apply_filters('acf/render_block', $output, $attributes);
          $output = apply_filters('acf/render_block/name=' . $attributes['block_name'], $output, $attributes);
        }

        reset_rows();
        wp_reset_postdata();
        $GLOBALS['ACF_BLOCK_ID'] = null;
        $GLOBALS['ACF_BLOCK_NAME'] = null;

        if ($cache_active) {
          acf_enable_cache();
        }
        
        return $output;
      },
    ) );
  }
});

function acf_gb_get_block_field_groups() {
  $groups = [];

  if ($_REQUEST['post']) {
    setup_postdata($post = get_post($_REQUEST['post']));
  }

  foreach (acf_get_field_groups() as $group) {
    $GLOBALS['ACF_BLOCK_NAME'] = $is_block = ($group['block_name']);

    if ($is_block) {

      if (acf_get_field_group_visibility($group)) {
        $groups[] = $group;
      }
    }

    $GLOBALS['ACF_BLOCK_NAME'] = null;
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

  var registeredCount = 0;

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
              acf_fields: '',
              acf_field_group: JSON.stringify(group),
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

        // setTimeout(function () {
        //   acf.do_action('ready', $('[data-block-id="' + block_id + '"]'));
        // }, 500);

        children.push(remote);

        return el('div', {
          className: 'acf-block-group-wrapper',
        }, children);
      },
      save: function () {
        // console.log('SAVE');
        return null;
      },
    })
  });

  console.log('Registered' + registeredCount + ' ACF Blocks');
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
<?php
}, 0);
