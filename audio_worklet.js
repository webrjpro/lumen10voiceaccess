// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Audio worklet support.
 * @module mod_lumen10voiceaccess/audio_worklet
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
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
