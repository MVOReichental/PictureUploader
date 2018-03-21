# MVO Picture Uploader

## Testing in Vagrant VM

Note: This was only tested with VirtualBox as provider.

   * Install Vagrant plugins (if not already installed):
      * VirtualBox guest plugin: `vagrant plugin install vagrant-vbguest`
      * Puppet install plugin: `vagrant plugin install vagrant-puppet-install`
   * Run `vagrant up` to setup the Vagrant VM.
   * The frontend will be available on the configured port on localhost ([http://localhost:8080](http://localhost:8080) by default)