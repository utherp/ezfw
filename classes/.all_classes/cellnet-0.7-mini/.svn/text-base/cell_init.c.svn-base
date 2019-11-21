#include <unistd.h>
#include <math.h>
#include <stdlib.h>
#include <stdint.h>

#include "celldef.h"
#include "debug.h"
#include "conf.h"
#define MAX_PATHS 8

int LAST_SHARP_DIVISOR = 2;

static int w;
static int h;

typedef struct {
    int skew;
    int step;
} _layout_axis;

typedef struct {
    const char *name;
    _layout_axis x;
    _layout_axis y;
    int lvl;
    int dirs;
    int path[MAX_PATHS][2];
} _cell_layout;

static const _cell_layout layouts[4] = {
  {
    .name = "3x3",
    .x = { .skew = 0, .step = 1 },
    .y = { .skew = 0, .step = 1 },
    .lvl = 0,
    .dirs = 8,
    .path = {
      { -1, -1 }, // NW
      {  0, -1 }, // N
      {  1, -1 }, // NE
      { -1,  0 }, // W
      {  1,  0 }, // E
      { -1,  1 }, // SW
      {  0,  1 }, // S
      {  1,  1 }  // SE
    }
  },
  {
    .name = "5x5",
    .x = { .skew = 0, .step = 1 },
    .y = { .skew = 0, .step = 1 },
    .lvl = 0,
    .dirs = 8,
    .path = {
      { -2, -2 }, // NW
      {  0, -1 }, // N
      {  2, -2 }, // NE
      { -1,  0 }, // W
      {  1,  0 }, // E
      { -2,  2 }, // SW
      {  0,  1 }, // S
      {  2,  2 }  // SE
    }
  },
  {
    .name = "Knight's Path",
    .x = { .skew = 0, .step = 1 },
    .y = { .skew = 0, .step = 1 },
    .lvl = 1,
    .dirs = 8,
    .path = {
      { -2, -1 }, // NWW
      { -1, -2 }, // NNW
      {  1, -2 }, // NNE
      {  2, -1 }, // NEE
      {  2,  1 }, // SEE
      {  1,  2 }, // SSE
      { -1,  2 }, // SSW
      { -2,  1 }  // SWW
    }
  },
  {
    .name = "Alternating Knight's Path / 3x3",
    .x = { .skew = 0, .step = 1 },
    .y = { .skew = 0, .step = 1 },
    .lvl = 1,
    .dirs = 8,
    .path = {
      { -2, -1 }, // NWW
      {  0, -1 }, // N
      {  2, -1 }, // NEE
      { -1,  0 }, // W
      {  2,  1 }, // SEE
      {  1,  0 }, // E
      { -2,  1 }, // SWW
      {  0,  1 }  // S
    }
  }
};

static inline uint32_t init_cell (cell_t *C, const _cell_layout *layout, int lvl, uint8_t *buf) {
    uint32_t init = 1;

    int x = C->pos.x;
    int y = C->pos.y;

    C->pix = buf;
    C->zones = 1;

    int i;

    for (i = 0; i < layout->dirs; i++) {
        int cx = x + layout->path[i][0];
        int cy = y + layout->path[i][1];
        if (cx < 0 || cx >= w || cy < 0 || cy >= h) continue;
        cell_t *next = C + ((w * layout->path[i][1]) + layout->path[i][0]);
        next->pos.x = cx;
        next->pos.y = cy;
        int tmp = ((w * layout->path[i][1]) + layout->path[i][0]);
        next->pix = (buf + tmp * 3);
        C->net.link[i] = next;
    }

    if (!lvl) return init;

    for (i = 0; i < layout->dirs; i++)
        if (C->net.link[i])
            init += init_cell(C->net.link[i], layout, lvl-1, C->net.link[i]->pix);

    return init;
}

int init_net (cell_t **cell_ref, int width, int height, uint8_t *buffer) {
    *cell_ref = calloc(width * height, sizeof(cell_t));
    cell_t *cells = *cell_ref;
    const _cell_layout *conf = &(layouts[0]);

    _debug_flow("Initializing '%s' net\n", conf->name);

    int x, y;

    w = width;
    h = height;

    int init = 0;

    for (y = conf->y.skew; y < h; y += conf->y.step) {
        int ytmp = w * y;
        for (x = conf->x.skew; x < w; x += conf->x.step) {
            cell_t *C = cells + ((y * w) + x);
            C->pos.x = x;
            C->pos.y = y;
            int tmp =ytmp + x;
            init += init_cell(C, conf, conf->lvl, (buffer + tmp * 3));
        }
    }

    _debug_flow("...done, Initialized %d cells\n", init);

    return init;
}

void clear_cell_state (cell_t *cell) {
    cell->sharp[0] = 0.0;
    cell->sharp[1] = 0.0;
    cell->sharp[2] = 0.0;
    cell->sharp[3] = 0.0;
/*    
    cell->edge[0] = 0.0;
    cell->edge[1] = 0.0;
    cell->edge[2] = 0.0;
    cell->edge[3] = 0.0;
*/
    /* clear for new frame */
    cell->pending = 0;
    return;
}

