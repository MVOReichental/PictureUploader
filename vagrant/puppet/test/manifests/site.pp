package { "htop":
  ensure => installed,
}

package { "vim":
  ensure => installed,
}

package { "git":
  ensure => installed,
}

package { "nodejs-legacy":
  ensure => installed,
}

package { "npm":
  ensure => installed,
}

package { "php5-cli":
  ensure => installed,
}

package { "php5-gd":
  ensure => installed,
}

file { "/etc/timezone":
  ensure  => present,
  content => "Europe/Berlin",
}

class { "apache":
  mpm_module    => "prefork",
  default_vhost => false,
  manage_user   => false,
  user          => "vagrant",
  group         => "vagrant",
}

apache::vhost { "localhost":
  port     => 80,
  docroot  => "/opt/mvo-picture-uploader/httpdocs",
  override => ["All"],
}

include apache::mod::php
include apache::mod::rewrite

class { "composer":
  command_name => "composer",
  target_dir   => "/usr/local/bin",
}

exec { "composer_install":
  path        => ["/usr/local/sbin", "/usr/local/bin", "/usr/sbin", "/usr/bin", "/sbin", "/bin"],
  command     => "composer install",
  cwd         => "/opt/mvo-picture-uploader",
  environment => ["HOME=/home/vagrant"],
  require     => Class["composer"],
}

exec { "npm_install_bower":
  path    => ["/usr/local/sbin", "/usr/local/bin", "/usr/sbin", "/usr/bin", "/sbin", "/bin"],
  command => "npm install -g bower",
  require => Package["nodejs-legacy", "npm"],
}

exec { "bower_install":
  path        => ["/bin", "/usr/bin", "/usr/local/bin"],
  cwd         => "/opt/mvo-picture-uploader",
  user        => "vagrant",
  command     => "bower install --config.interactive=false",
  environment => ["HOME=/home/vagrant"],
  require     => Exec["npm_install_bower"],
}