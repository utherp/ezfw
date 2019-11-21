#pragma once
#include <sys/socket.h>
#include <sys/un.h>
#include <stdint.h>

#define MAX_ZONES 32

typedef struct {
	char *name;
	void *signaler;
    uint8_t id;
    struct {
        uint32_t min;
        uint32_t pixels;
        uint32_t active;
        uint32_t strides;
    } count;
    uint32_t frame;
    uint32_t rate;
	uint32_t active;
    double vert;
    double tilt;
	double delta;
 	double last_delta;
	double trigger;
	char buf[255];
	uint32_t strides[];
} zonedef_t;

struct zone_collection_s {
	char *name;
	uint32_t count;
	void *signaler;
	zonedef_t zones[];
};
typedef struct zone_collection_s zone_collection_t;


