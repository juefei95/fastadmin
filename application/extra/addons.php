<?php

return array (
  'autoload' => false,
  'hooks' => 
  array (
    'user_delete_successed' => 
    array (
      0 => 'third',
    ),
    'user_logout_successed' => 
    array (
      0 => 'third',
    ),
    'module_init' => 
    array (
      0 => 'third',
    ),
    'action_begin' => 
    array (
      0 => 'third',
    ),
    'config_init' => 
    array (
      0 => 'third',
    ),
    'view_filter' => 
    array (
      0 => 'third',
    ),
  ),
  'route' => 
  array (
    '/third$' => 'third/index/index',
    '/third/connect/[:platform]' => 'third/index/connect',
    '/third/callback/[:platform]' => 'third/index/callback',
    '/third/bind/[:platform]' => 'third/index/bind',
    '/third/unbind/[:platform]' => 'third/index/unbind',
  ),
  'priority' => 
  array (
  ),
  'domain' => '',
);
