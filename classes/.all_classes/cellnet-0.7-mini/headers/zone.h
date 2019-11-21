#ifndef __ZONE_H__
#define __ZONE_H__

#include "zonedef.h"

zonedef_t *read_zone (char *name);
int write_zone (zonedef_t *this_zone);
double read_full_zone_delta ();
void mark_cells_in_zone (zonedef_t *zone);
int read_all_zones (uint32_t w, uint32_t h);
int read_zones_in (const char *path, char *namebuf, int bufleft);
int check_zone_trigger (zonedef_t *zone);
int calculate_zone_deltas (uint32_t supressor);
void zero_zone_deltas ();
int add_delta_to_bitzones (uint64_t bits, double delta, double vert, double tilt);

#endif
