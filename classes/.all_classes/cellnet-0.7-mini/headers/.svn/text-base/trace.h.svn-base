#ifndef __TRACE_H__
#define __TRACE_H__

#include "cell.h"

typedef struct {
    uint32_t flags:4;
    uint32_t back:1;  /* is step a backtrace */
    uint32_t dir:3;   /* direction, 0 - 7 */
} step_t;

typedef struct _trace_s {
    uint32_t trace_id;
    uint32_t strength;
    cell_t *start;
    trace_t *more;
    uint8_t nsteps;
    step_t steps[255];
} trace_t;

typedef struct {
    int id;
    int count;
    uint32_t hops[8];
    double dir;  /* this is the evaluated direction of affinity based on the hop counts (hops) */
} cellwave_t;



#endif
