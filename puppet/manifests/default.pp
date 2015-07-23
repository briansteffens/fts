Exec { path => [ "/bin/", "/sbin/" , "/usr/bin/", "/usr/sbin/" ] }

include system-update
include squid-proxy

file { '/etc/fts-server.conf':
  ensure => 'link',
  target => '/vagrant/server/config.php',
}

file { '/var/fts':
  ensure => 'directory',
  owner => 'vagrant',
  group => 'www-data',
  mode => '750',
}

file { '/bin/fts-server':
  ensure => 'link',
  target => '/vagrant/server/cli.php',
}

class { "::mysql::server":
  require => Class['squid-proxy'],
}

file { '/var/www':
  ensure => 'link',
  target => '/vagrant/server/web',
  require => Class["squid-proxy"],
}

class { "apache":
  require => File["/var/www"],
  user => "vagrant",
  default_vhost => false,
  mpm_module => 'prefork',
  service_ensure => 'running',
}

class { "apache::mod::php":
  require => File["/var/www"],
}

class { 'php-mysql':
  notify => Class['apache'],
}

apache::vhost { "fts":
  port => '80',
  docroot => '/var/www',
  rewrites => [
    {rewrite_rule => ['^(.*)$ /route.php?url=$1 [QSA,L]']},
  ],
}

mysql::db { 'fts':
  user => 'fts',
  password => '',
  host => 'localhost',
  grant => ['SELECT', 'INSERT', 'UPDATE', 'DELETE'],
  sql => '/vagrant/fts.sql',
}