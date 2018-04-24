<?php
/**
* @wordpress-plugin
* Plugin Name: Advanced Custom Blocks
* Plugin URI: https://github.com/rchipka/advanced-custom-blocks
* Description: ACF for Gutenberg blocks
* Version: 1.0.0
* Author: Robbie Chipka
* Author URI: https://github.com/rchipka
* GitHub Plugin URI: https://github.com/rchipka/advanced-custom-blocks
*/

add_filter('acf/location/rule_types', function ( $choices ) {
    $choices['gutenberg']['block_type'] = 'Block Type';
    return $choices;
}, 10, 1);

add_filter('acf/location/rule_values/block_type', function ( $choices, $data ) {
    return
      '<input type="text" data-ui="0" data-ajax="0" data-multiple="0" data-allow_null="0" ' .
        'id="acf_field_group-location-' . $data['group'] . '-' . $data['id'] . '-value" ' .
        'name="acf_field_group[location][' . $data['group'] . '][' . $data['id'] . '][value]" ' .
        'value="' . $data['value'] . '" />';
}, 10, 2);

add_filter('acf/location/rule_match/block_type', function ( $match, $rule, $options ) {
  if (empty($rule['value'])) {
    return true;
  }

  return ($rule['operator'] === '==') === ($_REQUEST['block']['name'] == $rule['value']);
}, 10, 3);

add_filter('acf/load_field', function ($field) {
  $fields = $_REQUEST['acf'] ?: $_REQUEST['block']['attributes']['acf_fields'];

  if (!$_REQUEST['isgutenberg'] || !$fields[$field['key']]) {
    return $field;
  }

  $field['value'] = $fields[$field['key']];

  return $field;
}, 10, 1);

add_action('wp_ajax_gutenberg_match_acf', function () {
  global $post;

  $fields = $_REQUEST['acf'] ?: $_REQUEST['block']['attributes']['acf_fields'];

  $response = array();

  if ($_REQUEST['post_id']) {
    setup_postdata($post = get_post($_REQUEST['post_id']));
  }

  foreach ( acf_get_field_groups() as $field_group ) {
    if (acf_get_field_group_visibility($field_group)) {
      $field_group['fields'] = acf_get_fields($field_group) ?: [];

      if ($field_group['fields']) {
        error_log(json_encode($_REQUEST));
        foreach ($field_group['fields'] as &$field) {
          $field['value'] = $fields[$field['key']];
        }

        ob_start();
        acf_render_fields($field_group['fields']);
        $field_group['html'] = ob_get_contents();
        ob_end_clean();
      } else {
        $field_group['html'] = '';
      }

      $response[] = $field_group;
    }
  }

  wp_send_json($response);
});

function recursive_unset(&$array, $unwanted_key) {
  if ($array[$unwanted_key]) {
    $value = $array[$unwanted_key];
    unset($array[$unwanted_key]);
    array_push($array, $value);
  }

  foreach ($array as &$value) {
    if (is_array($value)) {
      recursive_unset($value, $unwanted_key);
    }
  }
}

add_filter('gutenberg/template/block_attributes', function ($attributes, $block) {
  global $post;

  if ($_REQUEST['post_id']) {
    setup_postdata($post = get_post($_REQUEST['post_id']));
  }

  if (!($attributes['acf_fields'] = $_REQUEST['acf'])) {
    return $attributes;
  }

  $fields = json_decode(preg_replace_callback(
    '/"([^"]+)"\:/',
    function ($matches) {
      $key = $matches[1];

      $field = get_field_object($key);

      if ($field) {
        return '"' . $field['name'] . '":';
      }

      return '"_remove_key":';
    }, json_encode($_REQUEST['acf'])), true);

  if (is_array($fields)) {
    recursive_unset($fields, '_remove_key');
    foreach ($fields as $key => $value) {
      $attributes['acf_fields'][$key] = $value;
    }
  }

  return $attributes;
}, 10, 3);

add_filter('acf/block_content/type=core/paragraph', function ($content) {
  error_log($content);
  return preg_replace('/test/i', 'YARP', $content);
});

add_action('admin_footer', function () {
  if (!get_the_ID()) {
    return;
  }
  ?>
<script>
(function ($) {
  var groupElements = {},
      fieldGroups = {},
      fieldGroupForms = {};

  wp.hooks.addFilter('blocks.registerBlockType', 'acf', function (settings, name) {
    settings.attributes.acf_fields = { type: 'object' };
    return settings;
  }, 10);

  $(document).on('change', '.acf-form[data-block-id]', function () {
    var id = $(this).attr('id'),
        self = this,
        element = groupElements[id];

    console.log('got change', this);

    setTimeout(function () {
      if (element && element.props) {
        element.props.setAttributes({ _update: Math.random() });
      }
    });
  });

  wp.hooks.addFilter('blocks.template.ajaxRequest', 'acf', function (data, props, attributes) {
    console.log('ajaxRequest', attributes);
    if (!attributes.block_id) {
      return data;
    }

    var forms = $('.acf-form[data-block-id="' + attributes.block_id + '"]');
    
    var params = acf.serialize(forms);

    if (params && Object.keys(params).length > 0) {
      for (var key in params) {
        data[key] = params[key];
      }
    }

    return data;
  }, 10);

  wp.hooks.addFilter('blocks.BlockEdit', 'acf', wp.element.createHigherOrderComponent(function (element) {
    return function (props) {
      var block = wp.hooks.applyFilters(
            'blocks.template.BlockElement',
            wp.element.createElement(element, props, wp.element.createElement(wp.blocks.BlockEdit, props)),
            props),
          block_id = props.id,
          attributes = props.attributes;

      if (!props.isSelected) {
        return block;
      }

      attributes.block_id = block_id;

      wp.hooks.doAction('blocks.template.createElement', block, props, attributes);

      return block;
    };
  }));

  wp.hooks.addFilter('blocks.template.BlockElement', 'acf', function (element, props) {
    var block_id = props.id;

    console.log('acf block', props);
    if (!props.isSelected) {
      return element;
    }
    
    return loadACF(props.name, props, function (groups, justLoaded) {
      console.log('got groups', groups);

      if (groups.length < 1) {
        return;
      }

      var toolbar = [],
          before = [],
          after = [],
          side = []
          index = 0;

      groups.forEach(function (group) {
        if (group.position === 'acf_after_title') {
          before.push(group);
        } else if (group.position === 'normal') {
          after.push(group);
        } else if (group.position === 'toolbar') {
          toorbar.push(group);
        } else {
          side.push(group);
        }
      });

      var mapToFormElements = function (array, parent) {
        if (array.length < 1) {
          return null;
        }

        for (var i = 0; i < array.length; i++) {
          var group = array[i];
          var id = ['acf', group.ID, index++, block_id].join('-');

          fieldGroups[id] = group;
          groupElements[id] = element;

          if (!fieldGroupForms[id]) {
            fieldGroupForms[id] = $('<form>').html(group.html);
          }

          array[i] = wp.element.createElement('div', {
            id: id,
            className: 'acf-form',
            'data-block-id': block_id,
            // dangerouslySetInnerHTML: {
            //   __html: group.html
            // }
          });

          if (typeof parent === 'function') {
            array[i] = parent(group, array[i]);
          }
        }

        return array;
      }

      setTimeout(function () {
        $('.acf-form[data-block-id="' + block_id + '"]').each(function () {
          var self = this,
              id = $(this).attr('id'),
              form = fieldGroupForms[id];

          if (form && $(this).children()[0] !== form[0]) {
            $(this).html('');
            $(this).append(form.detach());

            setTimeout(function () {
              acf.do_action('ready', $(self));
            });
          }
        });

        if (justLoaded) {
          element.props.setAttributes({ _update: Math.random() });
        }
      });

      return [
        wp.element.createElement(
          wp.blocks.BlockControls,
          {},
          mapToFormElements(toolbar)
        ),
        wp.element.createElement(
          wp.blocks.InspectorControls,
          {},
          mapToFormElements(side, function (group, element) {
            return wp.element.createElement(
              wp.components.PanelBody,
              {
                title: group.title,
              },
              element
            );
          })
        ),
        mapToFormElements(before),
        element,
        mapToFormElements(after),
      ];
    }) || element;
  });

  function loadACF(id, block, callback) {
    if (fieldGroups.hasOwnProperty(id)) {
      return callback(fieldGroups[id], false);
    }

    fieldGroups[id] = [];

    $.ajax({
      url: ajaxurl,
      method: 'POST',
      data: $.param({
        action: 'gutenberg_match_acf',
        post_id: <?php echo json_encode(get_the_ID()); ?>,
        block: wp.hooks.applyFilters('blocks.template.serializeProps', block),
      }) + '&' + $.param(block.attributes.acf_fields || {}),
      success: function (field_groups) {
        callback(fieldGroups[id] = field_groups.sort(function (a, b) {
          return a.menu_order - b.menu_order;
        }), true);
      }
    });
  }
})(jQuery);
</script>
<?
}, 0);
