FTS server integration tests
============================

### Prerequisites ###
```
# aptitude install python python-pip
# pip install requests
```


### Configuration ###

Customize integration.test.conf with the URL of the server to test and a
base directory to use for tests. This testing directory will be deleted
and recreated multiple times during integration tests.


### Execution ###
```
$ ./integration.test.py
```
