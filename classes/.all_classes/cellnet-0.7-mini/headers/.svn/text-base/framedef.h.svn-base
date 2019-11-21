#pragma once
#include <sys/time.h>
#include <time.h>
#include <stdint.h>

#define FRAME_DATA_VERSION 4

typedef struct {
	uint32_t version;
	uint32_t width;
	uint32_t height;
	struct {
		time_t sec;
		time_t usec;
	} ts;
	uint8_t frame[1];
} frame_data_t;


