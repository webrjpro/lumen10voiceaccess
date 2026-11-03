// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
// Microphone processing stays off the UI thread. No recording or persistent buffer.
class Lumen10PCM extends AudioWorkletProcessor {
    constructor() {
        super();
        this.samples = [];
        this.position = 0;
        this.pcm = [];
    }
    process(inputs) {
        const channel = inputs[0]?.[0];
        if (!channel) { return true; }
        for (const sample of channel) { this.samples.push(sample); }
        const step = sampleRate / 16000;
        while (this.position + 1 < this.samples.length) {
            const index = Math.floor(this.position);
            const fraction = this.position - index;
            const sample = Math.max(-1, Math.min(1,
                this.samples[index] * (1 - fraction) + this.samples[index + 1] * fraction));
            this.pcm.push(Math.round(sample * (sample < 0 ? 32768 : 32767)));
            this.position += step;
            if (this.pcm.length === 320) {
                const data = new ArrayBuffer(640);
                const view = new DataView(data);
                let energy = 0;
                this.pcm.forEach((value, i) => {view.setInt16(i * 2, value, true); energy += (value / 32768) ** 2;});
                this.port.postMessage({pcm:data, level:Math.sqrt(energy / 320)}, [data]);
                this.pcm = [];
            }
        }
        const consumed = Math.min(Math.floor(this.position), this.samples.length);
        this.samples.splice(0, consumed);
        this.position -= consumed;
        return true;
    }
}
registerProcessor('lumen10-pcm', Lumen10PCM);
