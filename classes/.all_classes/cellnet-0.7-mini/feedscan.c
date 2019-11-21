#define __USE_BSD

#define STATIC_INLINE static

//#include "trace.h"
#include "cellnet.h"
#include "zone.h"
#include "framedef.h"
#include "debug.h"

#include "conf.h"

#include <math.h>
#include <stdint.h>

uint32_t frame_number = 1;

uint32_t MIN_ADJ_EDGES = 4;
uint32_t SUB_ADJ_EDGES = 2;
#include "cell.h"

uint32_t DIFF_FLOOR = 60;

/* time when the last frame was received */
struct timeval frame_ts;

/* the cellnet grid */
cell_t *cells = NULL;

static int bg_init = 1;


#define poke_pending_cell(c) \
  do { \
    if (c->pending) {    \
      entrant += poke_cell(c);   \
      c->pending = 0;   \
    }   \
  } while (0)

#define tally_cell_delta(c) \
  do {  \
    if ((c->set >= MIN_ADJ_EDGES) && c->total) { \
    double tmp = c->total * (c->set - SUB_ADJ_EDGES); \
/*    printf("%dx%d: set(%u) * delta(%0.3f) == %0.3f\n", c->pos.x, c->pos.y, c->set, c->total, tmp); */  \
      add_delta_to_bitzones(  \
        c->zones,  \
        tmp,    \
        (c->sharp[1] - c->sharp[3]),  /* vert < 0: horizontal, vert > 1 vertical */ \
        (c->sharp[2] - c->sharp[0])   /* tilt < 0: down slope, tilt > 1 up slope */ \
      );  \
    }   \
    c->last_set = c->set; \
    c->set >>= 1; \
  } while (0)


/*************************************
 * provided for the cell module,
 * these values are calculated before
 * the cells are thumped and give
 * details about the frame's luminance
 */
uint32_t ysum = 0;
uint32_t yhigh = 100;
uint32_t ylow = 160;
int32_t yavg = 130;
uint32_t yrange = 100;
int32_t yflr = 100;
uint32_t total_pix = 0;

/********************
 * from cellnet.c
 */
extern frame_data_t *frames[];
extern uint32_t width, height, pixels;

/**************************
 * used for suppressing
 * sudden lighting changes
 */

/*************************************************************************/

STATIC_INLINE uint8_t calc_pixel (cell_t *cell, uint32_t frame_number) {
    uint8_t * restrict bg = &cell->bg;
    uint8_t * restrict diff = &(cell->diff);
    uint8_t * restrict pix = cell->pix;
    uint8_t pixval = pix[0];
    int32_t pixdiff;
    
    /* get the luminance, add it to the sum and increment total pixels scanned */
    ysum += pixval;
    total_pix++;

    /* adjust high/low lumninance values */
    if (pixval < ylow) ylow = pixval;
    if (pixval > yhigh) yhigh = pixval;

    /* get pixel surface change as diff from the "background" pixel value */
    pixdiff = pixval - *bg;

    /* add the absolute surface change to diff */
    *diff = abs(pixdiff);

    /* adjust "background" pixel twords the current value */
    if (*diff < 3) *bg = pixval;
    else *bg += pixdiff>>2;

    if ((*diff) > DIFF_FLOOR) {
        cell->pending = 1;
    } else {
        cell->pending = 0;
    }

    /* return the cell's diff */
    return (*diff);
}


/************************************************
 * scan_linear:  scans across the cellnet in a
 * linear fashion... from offset 'pixoff', at a
 * stride of 'pixstep'
 *
 * returns the number of newly pending cells from
 * this scan
 */
STATIC_INLINE double scan_linear (int pixoff, int pixstep, uint32_t frame_number, uint32_t *total_divisor) {
    cell_t *cell = cells + pixoff;

    uint32_t i;
    double total = 0;

    for (i = pixels; i > 0; i -= pixstep, cell += pixstep) {
        total += calc_pixel(cell, frame_number);
    }

    return total;
}


/***************************************************
 * background_init: only called once when the first
 * frame is received.  It initializes each cell's
 * "background" values and performs a precursory 
 * thump on the entire network to get an initial
 * overlay of the video
 */
STATIC_INLINE int background_init (uint32_t frame_number) {
    int entrant = 0, i;
    bg_init = 0;
    cell_t *cell;

    /* clear the brightness descriptor values */
    total_pix = ysum = yavg = 0;
    ylow = yhigh = yrange = 100;

    for (cell = cells, i = pixels; i; i--, cell++, total_pix++) {
        /* set the "background" value to the current luminance value */
        cell->bg = cell->pix[0];
//        cell->bg[1] = cell->pix[1];
//        cell->bg[2] = cell->pix[2];

        ysum += cell->pix[0];
        if (ylow > cell->pix[0]) ylow = cell->pix[0];
        if (yhigh < cell->pix[0]) yhigh = cell->pix[0];

    }

    /* calculate brightness descriptor values */
    yavg = ysum / total_pix;
    yrange = yhigh - ylow;
    yflr = yrange - ylow - yavg;
    if (yflr < 1) yflr = 1;

    /* thump the cells */
    for (cell = cells, i = pixels; i; i--, cell++) 
        entrant += poke_cell(cell);

    for (cell = cells, i = pixels; i; i--, cell++)
        cell->set = cell->last_set = 0;

    return entrant;
}


/*******************************************************
 * feedscan_trip: this is the function which is called
 * when new frame has been received. 
 * Its parameter is the frame number... not specific, 
 * just as long as it increments, so the cell's know to
 * clear their cache.
 *
 * the return value represents the number of cells
 * which became entrant for this scan.
 */
int32_t last_yavg = 0;
int feedscan_trip (uint32_t frame_number) {

    double surface_delta = 0.0;

    int i, entrant = 0;
    uint8_t offset = frame_number & 1;

    /* initialize background on first frame */
    if (bg_init) return background_init(frame_number);

    /* get the frame timestamp */
    gettimeofday(&frame_ts, NULL);

    /* clear the brightness descriptor values */
    total_pix = ysum = yavg = 0;
    ylow = yhigh = yrange = 100;

    /* scans the frame, marking all pending cells */
//    surface_delta = scan_linear(0, 1, frame_number, &total_divisor);

    cell_t *cell = cells + offset;
    for (i = pixels>>4; i > 1; i--) { //, cell += 2) {
        surface_delta += calc_pixel(cell, frame_number);  cell += 2;
        surface_delta += calc_pixel(cell, frame_number);  cell += 2;
        surface_delta += calc_pixel(cell, frame_number);  cell += 2;
        surface_delta += calc_pixel(cell, frame_number);  cell += 2;
        surface_delta += calc_pixel(cell, frame_number);  cell += 2;
        surface_delta += calc_pixel(cell, frame_number);  cell += 2;
        surface_delta += calc_pixel(cell, frame_number);  cell += 2;
        surface_delta += calc_pixel(cell, frame_number);  cell += 2;
        if (!cell->net.go.w) cell++;
        else if (!cell->net.go.e) cell--;
    }
    /* calculate brightness descriptor values */
    yavg = ysum / total_pix;
    yrange = yhigh - ylow;

    /*************************************************
     * this is where we go back and make entrant all
     * the cells where the "poke" was deferred.
     */

    /* zero all zone deltas */
    zero_zone_deltas();

    cell = cells + offset;
//    if (frame_number & 1) cell++;
    for (i = pixels>>4; i > 1; i--) {
        poke_pending_cell(cell); cell += 2;
        poke_pending_cell(cell); cell += 2;
        poke_pending_cell(cell); cell += 2;
        poke_pending_cell(cell); cell += 2;
        poke_pending_cell(cell); cell += 2;
        poke_pending_cell(cell); cell += 2;
        poke_pending_cell(cell); cell += 2;
        poke_pending_cell(cell); cell += 2;
        if (!cell->net.go.w) cell++;
        else if (!cell->net.go.e) cell--;
    }

    /* final loop... add deltas to zones, boosted by the set value */

    cell = cells + offset;
    for (i = pixels>>4; i > 1; i--) {
        tally_cell_delta(cell); cell += 2;
        tally_cell_delta(cell); cell += 2;
        tally_cell_delta(cell); cell += 2;
        tally_cell_delta(cell); cell += 2;
        tally_cell_delta(cell); cell += 2;
        tally_cell_delta(cell); cell += 2;
        tally_cell_delta(cell); cell += 2;
        tally_cell_delta(cell); cell += 2;
        if (!cell->net.go.w) cell++;
        else if (!cell->net.go.e) cell--;
    }

    if (!(frame_number & 127))
        printf("lum(%d)\n", yavg);

    calculate_zone_deltas(abs(yavg - last_yavg));
    last_yavg = yavg;

    frame_number++;

//    printf("frame %u\n", frame_number);
    return entrant;
}

/*************************************************************************/


