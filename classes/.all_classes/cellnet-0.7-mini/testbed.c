#include "params.h"
#include "cell_init.h"
#include "framedef.h"
#include <stdint.h>
#include <stdlib.h>
#include <stdarg.h>
#include <time.h>
#include <string.h>
#include <errno.h>
#include "logger.h"
#include <sys/mman.h>
#include <fcntl.h>

void write_to_file (const char *filename, const char *format, ...);
const char *layout_name (int i);

//#include "bmp.h"
#include "img.h"

#define _BUFFER_COUNT_ 4

/********************
 * global variables
 *
 */


struct timeval frame_ts;
extern int NET_ENTRANT_DELTA;
extern int LUM_FLOOR_MULTIPLIER;
extern int SHARP_DIVISOR;
extern int LAST_SHARP_DIVISOR;

const char *input_filename;
const char *output_filename;
const char *log_filename;
int rand_power;
uint8_t *buffer;
uint32_t entr = 0;
uint32_t skipped = 0;

FILE *output;
size_t input_sz = 0;

int ystride, xstride;

cell_t *cells;

uint32_t frame_number;

/* command line modifiable parameters */
int generate_bmps;
int thump_val;

int map;

uint8_t high_lum;
uint8_t low_lum;
double max_sharp;
double min_sharp;
double max_edge;
double min_edge;

extern uint32_t ylow;
extern uint32_t yhigh;
extern uint32_t yavg;
extern uint32_t ysum;
extern uint32_t yrange;

uint32_t clr_count[7] = { 0, 0, 0, 0, 0, 0, 0 };
const double __rad_range[6] = { (PI/3), (PI/3*2), PI, (PI/3*4), (PI/3*5), (PI*2) };
const char *__rad_names[7] = { "Magenta", "Blue", "Cyan", "Green", "Yellow", "Red", "Grey" };
extern const char *__rad_names[7];
frame_data_t *frame;

uint32_t width, height, pixels;


int cellnet_init (int argc, char **argv) {

    gettimeofday(&frame_ts, NULL);

    /* set defaults */
    input_filename  = NULL;
    output_filename = NULL;
    xstride = 1;
    ystride = 1;
    thump_val = 3;
    map = 0;

    argspec_t args[] = {
      {
        .name   =   "Input",
        .desc   =   "Input filename containing YUV444 data",
        .longopt=   "input",
        .opt    =   'i',
        .type   =   STRING,
        .flags  =   ARG_REQ | ARG_PARAM_REQ | ARG_ICASE,
        .value  = { .STRING = &input_filename }
      },
      {
        .name   =   "Output",
        .desc   =   "Output filename of png edge overlay",
        .longopt=   "output",
        .opt    =   'o',
        .type   =   STRING,
        .flags  =   ARG_REQ | ARG_PARAM_REQ | ARG_ICASE,
        .value  = { .STRING = &output_filename }
      },
      {
        .name   =   "Xstep",
        .desc   =   "Step value across X plane",
        .longopt=   "xstep",
        .opt    =   'x',
        .type   =   INT,
        .flags  =   ARG_PARAM | ARG_PARAM_REQ | ARG_DEFAULT | ARG_ICASE,
        .value  =   { .INT = &xstride }
      },
      {
        .name   =   "Ystep",
        .desc   =   "Step value across Y plane",
        .longopt=   "ystep",
        .opt    =   'y',
        .type   =   INT,
        .flags  =   ARG_PARAM | ARG_PARAM_REQ | ARG_DEFAULT | ARG_ICASE,
        .value  =   { .INT = &ystride }
      },
      {
        .name   =   "Power",
        .desc   =   "Power of cellnet thump",
        .longopt=   "power",
        .opt    =   'p',
        .type   =   INT,
        .flags  =   ARG_PARAM | ARG_PARAM_REQ | ARG_DEFAULT | ARG_ICASE,
        .value  =   { .INT = &thump_val }
      },
      {
        .name   =   "Map",
        .desc   =   "Cell linking map",
        .longopt=   "map",
        .opt    =   'm',
        .type   =   INT,
        .flags  =   ARG_PARAM | ARG_PARAM_REQ | ARG_DEFAULT | ARG_ICASE,
        .value  =   { .INT = &map }
      }
    };

    if (parse_params(argc, argv, sizeof(args) / sizeof(argspec_t), args) == -1) return -1;

    if (layout_name(map) == NULL) {
        show_params_usage(11, args, "Invalid map id %u!", map);
        fprintf(stderr, "Valid maps are:\n");
        int i;
        const char *tmp;
        for (i = 0; (tmp = layout_name(i)) != NULL; i++)
            fprintf(stderr, " %3u:  '%s'\n", i, tmp);
        fprintf(stderr, "\n");
        return -1;
    }
    
    int input = open(input_filename, O_RDONLY);
    if (input == -1) {
        _show_error("Init", "Unable to open input file '%s': %s", input_filename, strerror(errno));
        return -1;
    }

    input_sz = lseek(input, 0, SEEK_END);
    lseek(input, 0, SEEK_SET);

    frame = mmap(NULL, input_sz, PROT_READ, MAP_PRIVATE, input, 0);
    if (frame == MAP_FAILED) {
        _show_error("Init", "Unable to mmap the input file: %s", strerror(errno));
        return -1;
    }

    width = frame->width;
    height = frame->height;
    pixels = width * height;

    buffer = frame->frame;

    close(input);

    output = fopen(output_filename, "w"); //O_WRONLY | O_CREAT, 0644);
    if (!output) { 
        _show_error("Init", "Unable to open output file '%s': %s", output_filename, strerror(errno));
        return -1;
    }

    /* initialize cellnet */
    init_net(&cells, map, width, height, buffer);

    return 0;
}

int cellnet_main () {

    _debug_verbose("Beginning scan...\n", 0);
    uint32_t x,y;
    int w = frame->width;
    int h = frame->height;

    double edge_diff = 0.0;
/*    
    if (rand_power) {
        printf("Randomizing %d pixels...\n", ystride);
        int trace_id = -1;
        srandom(time(NULL));
        do {
            double tmp = (double)random();
            tmp /= RAND_MAX;
            tmp *= w;
            x = (uint32_t)tmp;

            tmp = (double)random();
            tmp /= RAND_MAX;
            tmp *= h;
            y = (uint32_t)tmp;

            cell_t *cell = cells + (y * w) + x;

            thump_cell(cell, 1, thump_val, &trace_id, &edge_diff);

        } while (ystride--);
    } else {
*/


    uint8_t *tmp = buffer;
    int i;
    double sum = 0;
    for (i = 0; i < pixels; i++) {
        sum += *tmp;
        if (ylow > *tmp) ylow = (*tmp); //(*tmp)>>4;//(ylow + *tmp) / 2;
        if (yhigh < *tmp) yhigh = (*tmp);//>>4;//(yhigh + *tmp) / 2;
        tmp += 3;
    }
    sum = sum / (double)pixels;
    yavg = (uint32_t)sum;
//    yavg = ysum / pixels;
    yrange = yhigh - ylow;

    _show_warning("Lum Scan", "low: %d, high: %d, avg: %d, range: %d\n", ylow, yhigh, yavg, yrange);
            
        for (y = 0; y < h; y+=ystride) {
            cell_t *r = cells + (y * w);
            for (x = 0; x < w; x+=xstride) {
                int trace_id = -1;
                thump_opts opts = {
                    .frame = 1,
                    .dir = 0,
                    .strength = thump_val,
                    .trace_id = &trace_id,
                    .edge_diff = &edge_diff
                };
                thump_cell(r + x, &opts, 0);
            }
        }



//    }
    _debug_verbose("...scan finished\n", 0);


    double total_sharp = 0.0;

    cell_t *celltmp = cells;
    for (y = 0; y < pixels; y++, celltmp++) {
        double shtmp = celltmp->sharp[0] + celltmp->sharp[1] + celltmp->sharp[2] + celltmp->sharp[3];
//        printf("[%dx%d]: %0.3f\n", celltmp->pos.x, celltmp->pos.y, shtmp);
        total_sharp += shtmp;
    }


    _show_warning("Sharp", "pixels: %d,  total_sharp: %0.3f, avg: %0.3f", pixels, total_sharp, total_sharp / pixels);
//    write_to_file("low-high.lum", "%u, %u\n", low_lum, high_lum);
    write_to_file("count.clr", "%s: %u\n" "%s: %u\n" "%s: %u\n" "%s: %u\n" "%s: %u\n" "%s: %u\n" "%s: %u\n",
        __rad_names[0], clr_count[0], 
        __rad_names[1], clr_count[1], 
        __rad_names[2], clr_count[2], 
        __rad_names[3], clr_count[3], 
        __rad_names[4], clr_count[4], 
        __rad_names[5], clr_count[5], 
        __rad_names[6], clr_count[6] 
    );
    write_to_file("edge.range", "%0.3f - %0.3f\n", min_edge, max_edge);
    write_to_file("sharp.range", "%0.3f - %0.3f\n", min_sharp, max_sharp);
//    printf("Making bmp...\n");
    make_img(w, h, cells, output);

    fclose(output);
    munmap(frame, input_sz);
    fclose(logfile);

    return 0;
}

void write_to_file (const char *filename, const char *format, ...) {
    /* Guess we need no more than 100 bytes. */
    va_list ap;

    FILE *out = fopen(filename, "w");
    if (out == NULL) {
        _show_error("Write", "Unable to open file '%s': %s", filename, strerror(errno));
        return;
    }
    va_start(ap, format);
    vfprintf(out, format, ap);
    fclose(out);
    return;
}

