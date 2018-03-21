$packages = [
  "apt-transport-https",
  "ca-certificates",
  "git",
  "htop",
  "lsb-release",
  "vim",
]

package { $packages: }

apt::source { "packages.sury.org_php":
  location => "https://packages.sury.org/php",
  release  => "stretch",
  repos    => "main",
  key      => {
    id     => "DF3D585DB8F0EB658690A554AC0E47584A7A714D",
    source => "https://packages.sury.org/php/apt.gpg",
  },
  require  => Package["apt-transport-https", "ca-certificates"],
}

apt::pin { "packages.sury.org_php":
  priority   => 1000,
  originator => "deb.sury.org",
  require    => Apt::Source["packages.sury.org_php"],
}

$php_modules = [
  "cli",
  "gd",
  "mbstring",
  "xml",
]

$php_modules.each | $module | {
  package { "php7.1-${module}":
    require => [
      Apt::Source["packages.sury.org_php"],
      Apt::Pin["packages.sury.org_php"],
    ],
  }
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

package { "libapache2-mod-php7.1":
  require => [
    Class["apache"],
    Apt::Source["packages.sury.org_php"],
    Apt::Pin["packages.sury.org_php"],
  ],
}

class { "apache::mod::php":
  php_version => "7.1",
}
include apache::mod::rewrite

apache::vhost { "localhost":
  port     => 80,
  docroot  => "/opt/mvo-picture-uploader/httpdocs",
  override => ["All"],
}

class { "composer":
  command_name => "composer",
  target_dir   => "/usr/local/bin",
  require      => Package["php7.1-cli"],
}

exec { "composer_install":
  path        => ["/usr/local/sbin", "/usr/local/bin", "/usr/sbin", "/usr/bin", "/sbin", "/bin"],
  command     => "composer install",
  cwd         => "/opt/mvo-picture-uploader",
  environment => ["HOME=/home/vagrant"],
  require     => Class["composer"],
}

class { "nodejs":
  repo_url_suffix    => "8.x",
  npm_package_ensure => "present",
}

exec { "npm_install":
  path        => ["/bin", "/usr/bin", "/usr/local/bin"],
  cwd         => "/opt/mvo-picture-uploader/httpdocs",
  user        => "vagrant",
  command     => "npm install",
  environment => ["HOME=/home/vagrant"],
  require     => Class["nodejs"],
}