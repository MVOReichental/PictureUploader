$script = <<SHELL
    apt-get update

    for module in {puppetlabs-apache,puppetlabs-apt,puppet-nodejs,willdurand-composer}; do
        puppet module install --target-dir /opt/mvo-picture-uploader/vagrant/puppet/test/modules $module
    done
SHELL

Vagrant.configure(2) do |config|
    config.vm.box = "debian/stretch64"
    config.vm.network "private_network", ip: "192.168.100.3"
    config.vm.network "forwarded_port", guest: 80, host: 8080, auto_correct: true
    config.vm.synced_folder ".", "/opt/mvo-picture-uploader"
    config.vm.synced_folder "/data/pictures/Musikverein", "/data/pictures/Musikverein"
    config.vm.provision "shell",
        inline: $script
    config.vm.provision "puppet" do |puppet|
        puppet.environment_path = "vagrant/puppet"
        puppet.environment = "test"
    end
    config.vm.provision "file", source: "../mvo-website/.vagrant/machines/default/virtualbox/private_key", destination: ".ssh/id_rsa"
    config.puppet_install.puppet_version = "4.10.9"
end