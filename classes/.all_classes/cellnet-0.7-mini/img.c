#include <stdint.h>
#include <stdio.h>
#include <sys/types.h>
#include <sys/mman.h>
#include <string.h>
#include <errno.h>
#include <gd.h>
#include <fcntl.h>
#include <unistd.h>

#include "celldef.h"
#include "debug.h"
#include "traceclr.h"

typedef struct {
    uint8_t b;
    uint8_t g;
    uint8_t r;
    uint8_t pad;
} bmp_color_t;

extern uint32_t yrange;
extern uint32_t yavg;

/*******************************************************************************\
|******* Functions *************************************************************|
\*******************************************************************************/

extern uint32_t frame_number;

void make_img (uint32_t w, uint32_t h, cell_t *cells, FILE *fh) {

    int ftmp = open("/tmp/edges.out", O_RDWR | O_CREAT, 0666);
    if (ftmp == -1) {
        fprintf(stderr, "Error opening edges file: %s\n", strerror(errno));
        return;
    }
    lseek(ftmp, (w * h), SEEK_SET);
    write(ftmp, "\0", 1);
    lseek(ftmp, 0, SEEK_SET);
    char *outmap = mmap(NULL, (size_t)(w * h), PROT_WRITE | PROT_READ, MAP_SHARED, ftmp, 0L);

    if (outmap == MAP_FAILED) {
        fprintf(stderr, "Error mmaping file /tmp/edges.out: %s\n", strerror(errno));
        return;
    }

    int x, y;

    gdImagePtr img = gdImageCreateTrueColor(w, h);
    gdImageAlphaBlending(img, 0);
    gdImageSaveAlpha(img, 1);

    cell_t *ref = cells;
    for (y = 0; y < h; y++) {
//        cell_t *ref = cells + (w * y);

        for (x = 0; x < w; x++) {
            double dtmp = (ref->sharp[0] + ref->sharp[1] + ref->sharp[2] + ref->sharp[3]);
            dtmp = dtmp * (ref->sharp_count / 6);
            if (dtmp < 0) dtmp = 0;
            uint32_t tmp = (uint32_t)dtmp;
//            tmp <<= 4;
//            tmp = (uint32_t)(ref->texture * ((double)ref->sharp_count / 4));
            if (ref->sharp_count < 6) tmp = 0;
            if (tmp > 255) {
                printf("%dx%d: %d clipped to 255 (%0.3f %0.3f %0.3f %0.3f) \n", ref->pos.x, ref->pos.y, tmp, ref->sharp[0], ref->sharp[1], ref->sharp[2], ref->sharp[3]);
                tmp = 255;
            }
            *outmap = (char)tmp;

//            tmp *= (gdAlphaTransparent/2); 
            if (tmp > (gdAlphaTransparent/2)) tmp = (gdAlphaTransparent/2);
//            if (ref->sharp_count < 4) tmp = 0;

            tmp = gdAlphaTransparent - tmp;

            gdImageSetPixel(img, 
                x, y, 
                gdTrueColorAlpha(55, 251, 41, tmp)
            );

            ref++;
            outmap++;
//            ref = ref + 1;
        }

    }

    gdImagePng(img, fh);
    munmap(outmap, (w*h));
    close(ftmp);
    return;
}


