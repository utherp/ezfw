#ifndef __TRACECLR_H__
#define __TRACECLR_H__
typedef struct {
    uint8_t r;
    uint8_t g;
    uint8_t b;
} color_t;

typedef enum {
    Black = 0,
    DarkGrey,
    LightGrey,
    White,
    Red,
    Magenta,
    Blue,
    Cyan,
    Green,
    Yellow
} color_enum_u;

#define MAX_COLOR 9

#ifdef __DEBUGGING__
static const char *__color_names[(MAX_COLOR+1)] = {
    "Black",
    "DarkGrey",
    "LightGrey",
    "White",
    "Red",
    "Magenta",
    "Blue",
    "Cyan",
    "Green",
    "Yellow"
};    
#endif

static const color_t colors[(MAX_COLOR+1)] = {
    { .r =   0, .g =   0, .b =   0 }, // Black
    { .r =  80, .g =  80, .b =  80 }, // DarkGrey
    { .r = 160, .g = 160, .b = 160 }, // LightGrey
    { .r = 240, .g = 240, .b = 240 }, // White
    { .r = 240, .g =   0, .b =   0 }, // Red
    { .r = 240, .g =   0, .b = 240 }, // Magenta 
    { .r =   0, .g =   0, .b = 240 }, // Blue
    { .r =   0, .g = 240, .b = 240 }, // Cyan
    { .r =   0, .g = 240, .b =   0 }, // Green
    { .r = 240, .g = 240, .b =   0 }  // Yellow
};

#endif

