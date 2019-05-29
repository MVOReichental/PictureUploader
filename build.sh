#! /bin/bash

base_dir=$(dirname $(readlink -f $0))

(cd ${base_dir} && composer install --no-dev --ignore-platform-reqs)
(cd ${base_dir}/httpdocs && npm install)