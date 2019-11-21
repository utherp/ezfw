#pragma once

#include "debug.h"
#include "main.h"

#include <stdlib.h>
#include <memcache.h>
#include <string.h>

#define MC_HOST_PORT "localhost:11211"
#define MC_HOST "localhost"
#define MC_PORT 11211

#define MC_EXPIRY_SECONDS 30
char *read_from_memcache (char *key, int size);
void write_to_memcache (char *, void *, int, int);
void connect_to_memcache();

