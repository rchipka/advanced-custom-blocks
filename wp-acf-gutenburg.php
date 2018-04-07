<?php
/**
* @wordpress-plugin
* Plugin Name: ACF Gutenburg
* Plugin URI: https://github.com/rchipka/wp-acf-gutenburg
* Description: ACF integrations for Gutenburg blocks
* Version: 1.0.0
* Author: Robbie Chipka
* Author URI: https://github.com/rchipka
* GitHub Plugin URI: https://github.com/rchipka/wp-acf-gutenburg
*/

add_filter('acf/location/rule_types', function ( $choices ) {
    $choices['Gutenburg']['block_type'] = 'Block Type';
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
  return ($rule['operator'] === '==') === ($_REQUEST['block_type'] == $rule['value']);
}, 10, 3);

add_filter('acf/load_field', function ($field) {
  if (!$_REQUEST['isGutenburg'] || !$_REQUEST['acf'][$field['key']]) {
    return $field;
  }

  $field['value'] = $_REQUEST['acf'][$field['key']];

  return $field;
}, 10, 1);

add_action('wp_ajax_gutenburg_match_acf', function () {
  global $post;

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
          $field['value'] = $_REQUEST['acf'][$field['key']];
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

add_action('wp_ajax_gutenburg_filter_block', function () {
  global $post;

  if ($_REQUEST['post_id']) {
    setup_postdata($post = get_post($_REQUEST['post_id']));
  }

  $block = $_REQUEST['block'];
  $fields = $_REQUEST['acf'];

  $block['content'] = apply_filters('acf/block_content', $block['content'], $block, $fields);
  $block['content'] = apply_filters('acf/block_content/type=' . $block['name'], $block['content'], $block, $fields);

  $attributes = $block['attributes'];
  $block['attributes'] = apply_filters('acf/block_attributes', $block['attributes'], $block, $fields);
  $block['attributes'] = apply_filters('acf/block_attributes/type=' . $block['name'], $block['attributes'], $block, $fields);
  $block['attributes_changed'] = ($attributes != $block['attributes']);

  wp_send_json($block);
});

add_action('admin_footer', function () {
  if (!get_the_ID()) {
    return;
  }
  ?>
<script>
(function ($) {
  var fieldCache = {
    add: function (id, el) {
      (fieldCache[id] = fieldCache[id] || []).push(el);

      return fieldCache.get(id);
    },
    get: function (id) {
      return fieldCache[id] || [];
    }
  },
  loadedFields = {},
  contentCache = {}, xhrCache = {}, timeouts = {};

  wp.hooks.addFilter('blocks.getSaveElement', 'acf', function (element, blockType, attributes) {
    if (contentCache[attributes.block_id]) {
      console.log('Saving cached content', contentCache[attributes.block_id]);
      return wp.element.createElement(contentCache[attributes.block_id]);
    }

    return element;
  }, 10);

  wp.hooks.addFilter('blocks.registerBlockType', 'acf', function (settings, name) {
    settings.attributes.acf_fields = { type: 'string' };
    settings.attributes.block_id = { type: 'string' };
    return settings;
  }, 10);

  wp.hooks.addFilter( 'blocks.BlockEdit', 'acf', function (element) {
    return function (props) {
      var block_id = props.id;
      var block = wp.element.createElement(element, props);

      if (!block.props.attributes.block_id || block.props.attributes.block_id != block_id) {
        block.props.setAttributes({ block_id: block_id });
      }

      loadACF(props, function () {
        var savedFields = serializeACF(block_id),
            fieldsJSON = JSON.stringify(savedFields);

        if (block.props.attributes.acf_fields != fieldsJSON) {
          block.props.setAttributes({ acf_fields: fieldsJSON });
        }

        if (fieldsJSON.length < 3 || !block.props.isSelected) {
          return;
        }

        if (xhrCache[block_id]) {
          xhrCache[block_id].abort();
        }

        blockData = JSON.parse(JSON.stringify(props, function (key, value) {
          if (typeof value === 'function') {
            return undefined;
          }

          return value;
        }) || '{}');

        contentCache[block_id] = null;
        contentCache[block_id] = wp.blocks.serialize([
          wp.blocks.createBlock(block.props.name, block.props.attributes)
        ]);

        blockData.content = contentCache[block_id];

        $('.editor-post-publish-button').prop('disabled', true);
        xhrCache[block_id] = $.ajax({
          url: ajaxurl,
          method: 'POST',
          data: $.param({
            action: 'gutenburg_filter_block',
            post_id: <?php echo json_encode(get_the_ID()); ?>,
            block: blockData,
          }) + '&' + $.param(savedFields),
          success: function (response) {
            contentCache[block_id] = response.content;

            if (response.attributes_changed &&
                response.attributes_changed !== 'false') {
              block.props.setAttributes(response.attributes);
            }
          },
          complete: function () {
            $('.editor-post-publish-button').prop('disabled', false);
          }
        });
      });



      return block;
    };
  });

  function serializeACF(block_id) {
    return acf.serialize($(fieldCache.get(block_id)));
  }

  function loadACF(block, callback) {
    var fields = fieldCache.get(block.id);

    if (fields.length > 0) {
      $(fields).toggle(block.isSelected);
      clearTimeout(timeouts[block.id]);
      return (timeouts[block.id] = setTimeout(callback, 350));
    }

    if (loadedFields[block.id]) {
      return;
    }

    loadedFields[block.id] = true;

    var field_data = {};

    if (typeof block.attributes.acf_fields === 'string') {
      try { field_data = JSON.parse(block.attributes.acf_fields) } catch (e) {
        console.log('ACF-Gutenburg: Couldn\'t parse field_data', block.attributes);
      }
    }

    if (!field_data) {
      field_data = {};
    }

    $.ajax({
      url: ajaxurl,
      method: 'POST',
      data: $.param({
        action: 'gutenburg_match_acf',
        post_id: <?php echo json_encode(get_the_ID()); ?>,
        block_type: block.name,
        isGutenburg: true,
      }) + '&' + $.param(field_data),
      success: function (field_groups) {
        field_groups.sort(function (a, b) {
          return a.menu_order - b.menu_order;
        }).forEach(function (group) {
          var id = 'acf-field-group-' + group.ID;

          if ($('#' + id + '[data-block-id="' + block.id + '"]').length > 0) {
            return;
          }

          console.log('Got group', group);

          $form = $(
            '<form id="' + id + '"' +
            ' data-block-id="' + block.id + '"' +
            ' class="acf-field-group-form components-base-control"' +
            '></form>').toggle(block.isSelected);

          fieldCache.add(block.id, $form[0]);

          if (group.position === 'side') {
            $('.components-panel__body').append($form);
          } else {
            var $container = $('[data-block="' + block.id + '"]');

            if (group.position === 'acf_after_title') {
              $container.prepend($form);
            } else {
              $container.append($form);
            }
          }

          $form.html(group.html);

          acf.do_action('ready', $form);
        });

        callback();
      }
    });
  }
})(jQuery);
</script>
<?php
}, 0);
