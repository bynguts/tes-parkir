import { type ShaderComponentProps } from '../shader-mount.js';
import { type GemSmokeParams, type ImageShaderPreset } from '@paper-design/shaders';
export interface GemSmokeProps extends ShaderComponentProps, GemSmokeParams {
    /**
     * Suspends the component when the image is being processed.
     */
    suspendWhenProcessingImage?: boolean;
}
type GemSmokePreset = ImageShaderPreset<GemSmokeParams>;
export declare const defaultPreset: GemSmokePreset;
export declare const fluorescentPreset: GemSmokePreset;
export declare const firePreset: GemSmokePreset;
export declare const infraredPreset: GemSmokePreset;
export declare const gemSmokePresets: GemSmokePreset[];
export declare const GemSmoke: React.FC<GemSmokeProps>;
export {};
