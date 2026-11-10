<?php

return array (
  'autoload' => false,
  'hooks' => 
  array (
    'config' => 
    array (
      0 => 'checkin',
      1 => 'code2yellow',
    ),
    'app_init' => 
    array (
      0 => 'checkin',
      1 => 'checkinlegacy',
      2 => 'wallet',
    ),
    'user_delete_successed' => 
    array (
      0 => 'checkin',
      1 => 'third',
      2 => 'wallet',
    ),
    'user_register_successed' => 
    array (
      0 => 'checkin',
      1 => 'wallet',
    ),
    'app_dispatch' => 
    array (
      0 => 'checkinlegacy',
    ),
    'upgrade' => 
    array (
      0 => 'code2yellow',
      1 => 'remotecontrol',
    ),
    'epay_config_init' => 
    array (
      0 => 'epay',
    ),
    'addon_action_begin' => 
    array (
      0 => 'epay',
    ),
    'action_begin' => 
    array (
      0 => 'epay',
      1 => 'third',
    ),
    'user_logout_successed' => 
    array (
      0 => 'third',
    ),
    'module_init' => 
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
