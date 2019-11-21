#include <stdio.h>
#include <arpa/inet.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <errno.h>
#include <time.h>

#include "debug.h"

#include "zonedef.h"

#define _SIGNALER_ADDR_ "127.0.0.1"
#define _SIGNALER_DELTA_PORT_ 4224
#define _SIGNALER_TRIGGER_PORT_ 4225

int init_signaler ();

void signal_zone_active (zonedef_t *zone);
void signal_zone_inactive (zonedef_t *zone);

int init_zone_signaler(zonedef_t *zone);
void signal_zone_delta(zonedef_t *zone);
