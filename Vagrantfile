Vagrant.configure(2) do |config|
    config.vm.box = "ubuntu/trusty64"
    config.vm.hostname = "fts.dev"
    config.vm.network "forwarded_port", guest: 80, host: 9999

    config.vm.provision :puppet do |puppet|
        puppet.manifests_path = "puppet/manifests"
        puppet.module_path = "puppet/modules"
        puppet.options = ['--verbose']
    end
end