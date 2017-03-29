<?php
function pgi_votacion_lista_categoria($entrada, $post_id) {
  $vid = $entrada['votacion'];
  $terms = get_terms('votacion', array( 'hide_empty' => false, 'meta_key' => 'votacion_id', 'meta_value' => $vid, 'fields'=>'ids') );
  wp_set_object_terms( $post_id, $terms, 'votacion', false );
}
function pgi_votacion_formulario_listas($formulario) {
  add_action("pgi_post_process_$formulario", 'pgi_votacion_lista_categoria', 10, 2);
  add_filter('pgi_mapper_'.$formulario, 'pgi_listas_mapper', 10, 2);
}

function pgi_listas_mapper($mapper){
  return [
      'lista_tipo' => array( 'name' => 'lista_tipo', 'type' => 'text'),
      'lista_logo' => array( 'name' => 'lista_logo', 'type' => 'thumbnail'),
  ];
}


function pgi_votaciones_registrar_listas() {
  $supports = [ 'title', 'editor' ];
  if(function_exists("register_field_group"))
  {
    register_field_group( [
      'id' => 'pgi_lista',
      'title' => 'Lista',
      'fields' => [
          [ 'key' => 'lista_tipo', 'label' => 'Tipo de lista', 'name' => 'lista_tipo', 'type'=>'text']
        ],
      'location' => [[[ 'param' => 'post_type', 'operator' => '==', 'value' => 'lista', 'order_no' => 2, 'group_no' => 2 ]]],
      'options' => [ 'position' => 'normal', 'layout' => 'no_box', 'hide_on_screen' => []],
      'menu_order' => 2,
    ]);
  } else {
    $supports []= "custom-fields";
  }
  
  register_post_type('lista', [
    'labels' => [
        'name' => __('Listas', 'pgi-import'),
        'singular_name' => __('Listas', 'pgi-import'),
        'add_new' => __('Añadir nueva', 'pgi-import'),
        'add_new_item' => __('Añadir nueva lista', 'pgi-import'),
        'edit' => __('Editar', 'pgi-import'),
        'edit_item' => __('Editar lista', 'pgi-import'),
        'new_item' => __('Nueva lista', 'pgi-import'),
        'view' => __('Ver', 'pgi-import'),
        'view_item' => __('Ver lista', 'pgi-import'),
        'search_items' => __('Buscar listas', 'pgi-import'),
        'not_found' => __('No se han encontrado listas', 'pgi-import'),
        'not_found_in_trash' => __('No se han encontrado listas en la papelera', 'pgi-import')
    ],
    'public' => true,
    'hierarchical' => false,
    'has_archive' => false,
    'supports' => $supports,
    'can_export' => true,
    'taxonomies' => [  ], 
    'rewrite' => [ 'slug' => 'lista', 'with_front' => false ],
    'menu_icon' => 'dashicons-editor-ol',
    'menu_position' => 10,
    'show_in_menu' => false,
  ]);
}
add_action( 'init' , 'pgi_votaciones_registrar_listas' );

