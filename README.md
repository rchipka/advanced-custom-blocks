# Advanced Custom Blocks

ACF integrations for Gutenburg blocks

## What does it do?

This plugin adds ACF field group location rules for targeting Gutenburg blocks.

A gutenburg block is currently targeted by type name (`core/paragraph`, `core/latest-posts`, etc.)

Once a block is targeted, the field group will appear when editing the targeted block.

Where the fields appear on the post edit screen is currently determined by the field group's "Location" setting.

 * Normal - Fields will appear inside the block, below all other nested content/blocks
 * High (below title) - Fields will appear inside the block, above all other nested content/blocks
 * Side - Fields will appear in the side bar settings under the "Block" tab
 
## Usage

The plugin will save the field data to the target blocks "attributes" data.

You can customize the content generated based on your fields by creating a custom block type and targeting it, or by injecting your field content into the content of an existing block type.

*Note:* You will need to replace all occurrences of `wp.hooks` with `wp.acf_hooks` within the file `/plugins/advanced-custom-fields-pro/assets/js/acf-input.min.js`

## Filters

`acf/block_content`
`acf/block_content/type={block_name}`

function ($content, $block, $fields) {
  return $content
}

`acf/block_attributes`
`acf/block_attributes/type={block_name}`

function ($attributes, $block, $fields) {
  return $attributes
}


## Todo

 * Inject sidebar (block settings) fields via new Plugins API
 * Modify block content using new ServerSideRender component instead of requiring Gutenberg Block Partials?
