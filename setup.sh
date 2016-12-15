#!/bin/sh
#
# Setup the Docker image for the first time
#
echo "Setting up the development environment for docker presentation..."
which docker-compose >> /dev/null
result=$?
if [ $result == 1 ] ; then
	echo "It seems you don't have Docker installed (or docker-compose isn't working). Install docker from https://www.docker.com/"
	exit $result
fi


for i in {1..5}; do
	docker-compose ps >> /dev/null && break
	echo "Couldn't connect to Docker daemon. You might need to start it up.\n"
	echo "Start the native app OR run something like: docker-machine start default\n\n"
	read -n 1 -p "Press enter to try again..."
done

echo "\n\nRemoving the cheat sheets ..."
rm Dockerfile
