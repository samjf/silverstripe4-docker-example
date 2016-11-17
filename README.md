## Overview

This is a Docker example project of Silverstripe version 4.

This is was for an example of setting up a basic Dockerfile for easy environment setup and team development.

### Useful commands

 * docker build -t my-silverstripe4 .
  - This will build the dockerfile found in the current directory
  - It will tag the image as my-silverstripe4
 * docker run -it --rm -p 8080:80 --name my-running-app my-silverstripe4
  - this will run a new container from the image my-silverstripe4
  - it will name it "my-running-app"
  - it runs it with the ability to interact and have terminal '-it'
  - binds port 8080 to the containers port 80
  - removes the container after it receives the stop signal '-rm'
 * docker-compose build web
  - builds the service 'web' that we defined in docker-compose.yml
 * docker-compose up
  - runs the services and their dependenices
