<?php

namespace HM\Platform\Enhanced_Search;

add_action( 'plugins_loaded', __NAMESPACE__ . '\\bootstrap' );
add_filter( 'hm_platform_healthchecks', __NAMESPACE__ . '\\add_elasticsearch_healthcheck' );
