#ifndef _CELLDEF_H_
#define _CELLDEF_H_

#define PI 3.1415926535897932384626433832795
#define PHI 1.61803399

/**********/

typedef struct _cell_s cell_t;

typedef struct _clinks_s {
    cell_t *nw;
    cell_t *n;
    cell_t *ne;
    cell_t *w;
    cell_t *e;
    cell_t *sw;
    cell_t *s;
    cell_t *se;
} clinks_t;

typedef struct _pos_s {
    uint16_t x;
    uint16_t y;
} pos_t;

typedef union {
    clinks_t go;
    cell_t *link[8];
} cellnet_t;

struct _cell_s {
    cellnet_t net;
    pos_t pos;
    struct {
        double u;
        double v;
    } chroma;

    uint32_t frame;
    double radians;
    double radius;
    double total;
    double sharp[4];
    double last_sharp[4];

    uint64_t zones;

    int16_t set;
    int16_t last_set;
    uint8_t *pix;
    uint8_t pending;
    uint8_t diff;
    uint8_t bg;
};

#endif

