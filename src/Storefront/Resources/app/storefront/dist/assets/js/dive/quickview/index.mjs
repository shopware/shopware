import { D as c, b as r, c as l, a as d } from "../../chunks/FileTypes-OWYPzqcN.mjs";
import "three";
const p = {
  ...c
}, V = async (t, a) => {
  const e = new r(a);
  e.mainView.camera.position.set(0, 1, 2);
  const o = await new l().setFromURL(t);
  e.scene.root.add(o), o.placeOnFloor();
  const i = new d(
    e.mainView.camera,
    e.mainView.canvas
  );
  i.focusObject(o), e.clock.addTicker(i);
  const s = Object.assign(e, { orbitController: i }), n = e.dispose.bind(e);
  return s.dispose = async () => {
    i.dispose(), await n();
  }, s;
};
export {
  V as QuickView,
  p as QuickViewDefaultSettings
};
