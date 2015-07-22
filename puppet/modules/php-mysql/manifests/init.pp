class php-mysql {
  package { "php5-mysql":
    ensure  => present,
    require => Class["squid-proxy"],
  }
}