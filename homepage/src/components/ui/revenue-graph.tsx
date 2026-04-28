"use client";

import React, { useEffect } from "react";
import { interpolate, spring, useCurrentFrame, useVideoConfig } from "remotion";

const LINE_POINTS: Array<[number, number]> = [
  [0, 70],
  [16, 55],
  [32, 62],
  [48, 38],
  [64, 45],
  [80, 22],
  [100, 12],
];

export function RevenueGraph({
  kpiTarget,
  accentColor = "#22c55e",
  textElementId,
}: {
  kpiTarget: number;
  accentColor?: string;
  textElementId?: string;
}) {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();

  // "animasi uang nya" (the money counting animation)
  const kpiSpring = spring({
    frame: frame - 10,
    fps,
    config: { damping: 20, stiffness: 80, mass: 1 },
    durationInFrames: 60,
  });
  
  const kpiValue = Math.floor(kpiSpring * kpiTarget);

  useEffect(() => {
    if (textElementId) {
      const el = document.getElementById(textElementId);
      if (el) {
        el.innerText = "Rp " + kpiValue.toLocaleString("id-ID");
      }
    }
  }, [kpiValue, textElementId]);

  // "dan grafik nya" (the line graph animation)
  const linePath = LINE_POINTS.map(([x, y], i) => `${i === 0 ? "M" : "L"}${x},${y}`).join(" ");
  const lineProgress = interpolate(
    frame,
    [15, 65],
    [0, 1],
    { extrapolateLeft: "clamp", extrapolateRight: "clamp" },
  );

  return (
    <div style={{ position: "absolute", inset: 0, overflow: "hidden" }}>
      <svg
        viewBox="-5 -5 110 110"
        preserveAspectRatio="none"
        style={{ width: "100%", height: "100%", opacity: 0.6 }}
      >
        <path
          d={linePath}
          fill="none"
          stroke={accentColor}
          strokeWidth="2.5"
          strokeLinecap="round"
          strokeLinejoin="round"
          pathLength={1}
          strokeDasharray={1}
          strokeDashoffset={1 - lineProgress}
        />
        {LINE_POINTS.map(([px, py], i) => {
          const dotProgress = interpolate(
            lineProgress,
            [
              i / (LINE_POINTS.length - 1),
              i / (LINE_POINTS.length - 1) + 0.05,
            ],
            [0, 1],
            { extrapolateLeft: "clamp", extrapolateRight: "clamp" },
          );
          return (
            <circle
              key={i}
              cx={px}
              cy={py}
              r={2.5}
              fill={accentColor}
              opacity={dotProgress}
            />
          );
        })}
      </svg>
      {/* Light glow at bottom to simulate an area chart feeling */}
      <div 
        style={{ 
          position: "absolute", 
          inset: 0, 
          background: `linear-gradient(to top, ${accentColor}3A 0%, transparent 70%)`,
          pointerEvents: "none"
        }} 
      />
    </div>
  );
}
