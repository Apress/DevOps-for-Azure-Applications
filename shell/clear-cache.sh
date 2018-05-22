#!/bin/bash

BASE_DIR=`dirname $0`"/.."
BASE_DIR=`cd ${BASE_DIR}; pwd`

umask "u=rwx,g=rwx,o=rx"

echo ""
echo "** Clear Magento Cache - `date`"

echo ""
echo "** Base Directory: ${BASE_DIR}"

echo ""
echo "** Deleting Files"

rm -f ${BASE_DIR}/var/resource_config.json

for DIR in ${BASE_DIR}/var/*; do
  rm -rf ${DIR}/*
done

echo ""
echo "** Remember to restart the Webservice"

#sudo /sbin/service httpd restart