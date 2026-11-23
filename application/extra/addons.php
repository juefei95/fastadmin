<?php

return array (
  'autoload' => false,
  'hooks' => 
  array (
    'config' => 
    array (
      0 => 'checkin',
      1 => 'code2yellow',
      2 => 'remotecontrol',
    ),
    'app_init' => 
    array (
      0 => 'checkin',
      1 => 'checkinlegacy',
      2 => 'cms',
      3 => 'wallet',
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
      0 => 'cms',
      1 => 'code2yellow',
      2 => 'remotecontrol',
    ),
    'action_begin' => 
    array (
      0 => 'cms',
      1 => 'epay',
      2 => 'third',
    ),
    'view_filter' => 
    array (
      0 => 'cms',
      1 => 'third',
    ),
    'user_sidenav_after' => 
    array (
      0 => 'cms',
    ),
    'xunsearch_config_init' => 
    array (
      0 => 'cms',
    ),
    'xunsearch_index_reset' => 
    array (
      0 => 'cms',
    ),
    'epay_config_init' => 
    array (
      0 => 'epay',
    ),
    'addon_action_begin' => 
    array (
      0 => 'epay',
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
  ),
  'route' => 
  array (
    '/cms/$' => 'cms/index/index',
    '/cms/t/[:diyname]$' => 'cms/tag/index',
    '/cms/p/[:diyname]$' => 'cms/page/index',
    '/cms/s$' => 'cms/search/index',
    '/cms/d/[:diyname]$' => 'cms/diyform/index',
    '/cms/d/[:diyname]/post' => 'cms/diyform/post',
    '/cms/d/[:diyname]/[:id]' => 'cms/diyform/show',
    '/cms/special/[:diyname]' => 'cms/special/index',
    '/u/[:id]' => 'cms/user/index',
    '/cms/[:diyname]$' => 'cms/channel/index',
    '/cms/[:catename]/[:id]$' => 'cms/archives/index',
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
