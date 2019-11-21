#ifndef __PATH_H__
#define __PATH_H__

#include <stdint.h>

typedef struct {
  int8_t x;       /* x pixel stride */
  int8_t y;       /* y pixel stride */
  struct _junc *pass;   /* junc to use at the target pixel if evaluation passes*/
  struct _junc *fail;   /* junc to use at the target pixel if evaluation fails */
} path_t;

typedef struct _junc {
  uint8_t marker;   /* a junc marker used for... */
  uint8_t repeat;   /* repeat back to junc marker */
  uint8_t anchor;   /* resistance to miss ripple */
  uint8_t bouy;     /* resistance to match ripple */
  path_t *link[8];  /* ordered links from this junc */
} junc_t;

int junc_trace (junc_t *junc, int x, int y, int max_depth, uint8_t btree[], int depth);
int init_paths ();

#ifndef __PATH_C__
  extern junc_t *knights_moves;
  extern junc_t *kings_moves;
  extern junc_t *knights_to_kings;
#endif

#endif
