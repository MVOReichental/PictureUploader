$script = <<SHELL
    aptitude update
    puppet module install puppetlabs-apache
    puppet module install willdurand-composer
SHELL

Vagrant.configure(2) do |config|
    config.vm.box = "gutocarvalho/debian8x64"
    config.vm.network "forwarded_port", guest: 80, host: 8080, auto_correct: true
    config.vm.synced_folder ".", "/opt/mvo-picture-uploader"
    config.vm.provision "shell",
        inline: $script
    config.vm.provision "puppet" do |puppet|
        puppet.environment_path = "vagrant/puppet"
        puppet.environment = "test"
    end
end