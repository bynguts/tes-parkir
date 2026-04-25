import type { vec4 } from '../types.js';
import type { ShaderMotionParams } from '../shader-mount.js';
import type { ShaderSizingParams, ShaderSizingUniforms } from '../shader-sizing.js';
export declare const gemSmokeMeta: {
    readonly maxColorCount: 6;
};
/**
 * Animated color fields placed over uploaded logo shape; gives the illusion of smoky noise
 * behind the glassy shape.
 *
 * Fragment shader uniforms:
 * - u_time (float): Animation time
 * - u_resolution (vec2): Canvas resolution in pixels
 * - u_image (sampler2D): Pre-processed source image texture (R = edge gradient, G = alpha)
 * - u_imageAspectRatio (float): Aspect ratio of the source image
 * - u_colors (vec4[]): Up to 6 smoke colors in RGBA
 * - u_colorsCount (float): Number of active colors
 * - u_colorBack (vec4): Background color in RGBA
 * - u_innerDistortion (float): Power of smoke distortion inside the input shape (0 to 1)
 * - u_outerDistortion (float): Power of smoke distortion outside the input shape (0 to 1)
 * - u_outerGlow (float): Visibility of smoke shape outside the input shape (0 to 1)
 * - u_innerGlow (float): Visibility of smoke shape inside the input shape (0 to 1)
 * - u_colorInner (vec4): Additional color inside the input shape, mixing with smoke (RGBA)
 * - u_offset (float): Vertical offset of smoke inside the shape (-1 to 1)
 * - u_angle (float): Smoke direction in degrees (0 to 360)
 * - u_size (float): Size of smoke shape relative to the image box (0 to 1)
 *
 * Vertex shader outputs (used in fragment shader):
 * - v_imageUV (vec2): UV coordinates for sampling the source image, with fit, scale, rotation, and offset applied
 * - v_objectUV (vec2): Normalized UV coordinates with scale, rotation, and offset applied
 *
 * Vertex shader uniforms:
 * - u_resolution (vec2): Canvas resolution in pixels
 * - u_pixelRatio (float): Device pixel ratio
 * - u_originX (float): Reference point for positioning world width in the canvas (0 to 1)
 * - u_originY (float): Reference point for positioning world height in the canvas (0 to 1)
 * - u_worldWidth (float): Virtual width of the graphic before it's scaled to fit the canvas
 * - u_worldHeight (float): Virtual height of the graphic before it's scaled to fit the canvas
 * - u_fit (float): How to fit the rendered shader into the canvas dimensions (0 = none, 1 = contain, 2 = cover)
 * - u_scale (float): Overall zoom level of the graphics (0.01 to 4)
 * - u_rotation (float): Overall rotation angle of the graphics in degrees (0 to 360)
 * - u_offsetX (float): Horizontal offset of the graphics center (-1 to 1)
 * - u_offsetY (float): Vertical offset of the graphics center (-1 to 1)
 * - u_imageAspectRatio (float): Aspect ratio of the source image
 *
 */
export declare const gemSmokeFragmentShader: string;
export declare const POISSON_CONFIG_OPTIMIZED: {
    measurePerformance: boolean;
    workingSize: number;
    iterations: number;
};
export declare function toProcessedGemSmoke(file: File | string): Promise<{
    imageData: ImageData;
    pngBlob: Blob;
}>;
export interface GemSmokeUniforms extends ShaderSizingUniforms {
    u_colorBack: [number, number, number, number];
    u_colors: vec4[];
    u_colorsCount: number;
    u_image: HTMLImageElement | string | undefined;
    u_innerDistortion: number;
    u_outerDistortion: number;
    u_outerGlow: number;
    u_innerGlow: number;
    u_colorInner: [number, number, number, number];
    u_offset: number;
    u_angle: number;
    u_size: number;
    u_shape: (typeof GemSmokeShapes)[GemSmokeShape];
    u_isImage: boolean;
}
export interface GemSmokeParams extends ShaderSizingParams, ShaderMotionParams {
    colors?: string[];
    colorBack?: string;
    image?: HTMLImageElement | string | undefined;
    innerDistortion?: number;
    outerDistortion?: number;
    outerGlow?: number;
    innerGlow?: number;
    colorInner?: string;
    offset?: number;
    angle?: number;
    size?: number;
    shape?: GemSmokeShape;
}
export declare const GemSmokeShapes: {
    readonly none: 0;
    readonly circle: 1;
    readonly daisy: 2;
    readonly diamond: 3;
    readonly metaballs: 4;
};
export type GemSmokeShape = keyof typeof GemSmokeShapes;
