var S = Object.defineProperty;
var A = (r, s, t) => s in r ? S(r, s, { enumerable: !0, configurable: !0, writable: !0, value: t }) : r[s] = t;
var i = (r, s, t) => A(r, typeof s != "symbol" ? s + "" : s, t);
import { B as ot, b as ht, s as lt, e as ct, D as dt, d as pt, j as ut, i as ft, p as _t, q as gt, r as mt, c as Et, v as Dt, t as wt, w as yt, h as Mt, g as xt, f as It, k as Pt, x as Vt, m as bt, l as Ht, u as zt, n as vt, F as Ot, G as St, o as At, S as Gt, y as Rt } from "./chunks/FileTypes-OWYPzqcN.mjs";
import { U as d } from "./chunks/PerspectiveCamera-BFzE2TQU.mjs";
import { C as Yt, b as jt, D as Ct, a as Lt, H as Nt, P as Xt } from "./chunks/PerspectiveCamera-BFzE2TQU.mjs";
import { A as I, a as P, b as V } from "./chunks/AxisHelperColors-JLBHYQDi.mjs";
import { e as Zt, d as qt, c as Qt } from "./chunks/AxisHelperColors-JLBHYQDi.mjs";
import { Object3D as f, Color as b, Vector3 as c, TorusGeometry as H, MeshBasicMaterial as g, Mesh as p, MathUtils as v, Euler as G, CylinderGeometry as E, BoxGeometry as R, PlaneGeometry as w } from "three";
import { f as kt, i as Ut } from "./chunks/findInterface-DbJ5qzbc.mjs";
import { g as Kt, i as Jt } from "./chunks/isFileTypeSupported-BSpswPHU.mjs";
import { F as te, N as ee } from "./chunks/network-error-BONfHWQq.mjs";
import { F as se, P as ie } from "./chunks/parse-error-DfOPyLWM.mjs";
import { i as ae, a as oe } from "./chunks/PovSchema-DWWvr_ED.mjs";
class y extends f {
  constructor(t, e, n, o, a) {
    super();
    i(this, "isHoverable", !0);
    i(this, "isDraggable", !0);
    i(this, "parent", null);
    i(this, "axis");
    i(this, "_color", new b(16711935));
    i(this, "_colorHover");
    i(this, "_hovered");
    i(this, "_highlight");
    i(this, "_lineMaterial");
    i(this, "_colliderMesh");
    this.name = "DIVERadialHandle", this.axis = t, this._color.set(a), this._colorHover = this._color.clone().multiplyScalar(2), this._hovered = !1, this._highlight = !1;
    const h = new H(e, 0.01, 13, 48, n);
    this._lineMaterial = new g({
      color: a,
      depthTest: !1,
      depthWrite: !1
    });
    const l = new p(h, this._lineMaterial);
    l.layers.mask = d, l.renderOrder = 1 / 0, this.add(l);
    const _ = new H(e, 0.1, 3, 48, n), D = new g({
      color: 16711935,
      transparent: !0,
      opacity: 0.15,
      depthTest: !1,
      depthWrite: !1
    });
    this._colliderMesh = new p(_, D), this._colliderMesh.visible = !1, this._colliderMesh.layers.mask = d, this._colliderMesh.renderOrder = 1 / 0, this.add(this._colliderMesh), this.lookAt(o);
  }
  set debug(t) {
    this._colliderMesh.visible = t;
  }
  get highlight() {
    return this._highlight;
  }
  set highlight(t) {
    this._highlight = t, this._lineMaterial.color = this._highlight || this._hovered ? this._colorHover : this._color;
  }
  get forwardVector() {
    return new c(0, 0, 1).applyQuaternion(this.quaternion).normalize();
  }
  get rightVector() {
    return new c(1, 0, 0).applyQuaternion(this.quaternion).normalize();
  }
  get upVector() {
    return new c(0, 1, 0).applyQuaternion(this.quaternion).normalize();
  }
  reset() {
    this._lineMaterial.color = this._color;
  }
  onPointerEnter() {
    this._hovered = !0, this.parent && this.parent.onHandleHover(this, !0);
  }
  onPointerLeave() {
    this._hovered = !1, this.parent && this.parent.onHandleHover(this, !1);
  }
  onDragStart() {
    this.parent && this.parent.onHandleDragStart(this);
  }
  onDrag(t) {
    this.parent && this.parent.onHandleDrag(this, t);
  }
  onDragEnd() {
    this.parent && this.parent.onHandleDragEnd(this);
  }
}
function u(r, s) {
  const t = (r + "e").split("e");
  return +(t[0] + "e" + (+t[1] + (s || 0)));
}
function T(r, s = 0) {
  const t = u(r, +s);
  return u(Math.ceil(t), -s);
}
function Y(r, s = 0) {
  const t = u(r, +s);
  return u(Math.floor(t), -s);
}
function O(r, s = 0) {
  if (r < 0) return -O(-r, s);
  const t = u(r, +s);
  return u(Math.round(t), -s);
}
function j(r, s, t) {
  return Math.atan2(
    r.clone().cross(s).dot(t),
    s.clone().dot(r)
  );
}
function C(r, s = 0) {
  const t = u(r, +s);
  return u(Math.round(t), -s).toFixed(s);
}
function L(r, s = 0) {
  const t = u(r, +s);
  return u(Math.trunc(t), -s);
}
function N(r) {
  return (v.radToDeg(r) + 360) % 360;
}
function X(r) {
  return v.degToRad(r);
}
const F = {
  ceilExp: T,
  floorExp: Y,
  roundExp: O,
  toFixedExp: C,
  truncateExp: L,
  signedAngleTo: j,
  radToDeg: N,
  degToRad: X
};
class Z extends f {
  constructor(t) {
    super();
    i(this, "children");
    i(this, "_controller");
    i(this, "_startRot");
    this.name = "DIVERotateGizmo", this.children = [], this._startRot = null, this._controller = t, this.add(
      new y(
        "x",
        1,
        Math.PI / 2,
        new c(1, 0, 0),
        I
      )
    ), this.add(
      new y(
        "y",
        1,
        -Math.PI / 2,
        new c(0, 1, 0),
        P
      )
    ), this.add(
      new y(
        "z",
        1,
        Math.PI / 2,
        new c(0, 0, 1),
        V
      )
    );
  }
  set debug(t) {
    this.children.forEach((e) => {
      e.debug = t;
    });
  }
  reset() {
    this.children.forEach((t) => {
      t.reset();
    });
  }
  handleHighlight(t, e, n) {
    this.children.forEach((o) => {
      n ? o.highlight = o.axis === t && n : o.highlight = o.axis === t && e;
    });
  }
  onHandleHover(t, e) {
    this._startRot || this.parent && this.parent.parent && (this.parent.parent.onHover("rotate", t.axis, e), this.handleHighlight(t.axis, e, !1));
  }
  onHandleDragStart(t) {
    if (!this.parent || !this.parent.parent) return;
    const e = this.parent.parent.object;
    e && (this._startRot = e.rotation.clone(), this.handleHighlight(t.axis, !0, !0));
  }
  onHandleDrag(t, e) {
    if (!this._startRot || !this.parent || !this.parent.parent || !("onChange" in this.parent.parent)) return;
    const n = e.dragCurrent.clone().sub(this.parent.parent.position).normalize(), o = e.dragStart.clone().sub(this.parent.parent.position).normalize(), a = F.signedAngleTo(
      o,
      n,
      t.forwardVector
    ), h = new G(
      this._startRot.x + t.forwardVector.x * a,
      this._startRot.y + t.forwardVector.y * a,
      this._startRot.z + t.forwardVector.z * a
    );
    this.parent.parent.onChange(void 0, h);
  }
  onHandleDragEnd(t) {
    this._startRot = null, this.handleHighlight(t.axis, !1, !1);
  }
}
class M extends f {
  constructor(t, e, n, o) {
    super();
    i(this, "isHoverable", !0);
    i(this, "isDraggable", !0);
    i(this, "parent", null);
    i(this, "axis");
    i(this, "_color", new b(16711935));
    i(this, "_colorHover");
    i(this, "_hovered");
    i(this, "_highlight");
    i(this, "_lineMaterial");
    i(this, "_colliderMesh");
    this.name = "DIVEAxisHandle", this.axis = t, this._color.set(o), this._colorHover = this._color.clone().multiplyScalar(2), this._highlight = !1, this._hovered = !1;
    const a = new E(0.01, 0.01, e, 13);
    this._lineMaterial = new g({
      color: o,
      depthTest: !1,
      depthWrite: !1
    });
    const h = new p(a, this._lineMaterial);
    h.layers.mask = d, h.renderOrder = 1 / 0, h.rotateX(Math.PI / 2), h.translateY(e / 2), this.add(h);
    const l = new E(0.1, 0.1, e, 3), _ = new g({
      color: 16711935,
      transparent: !0,
      opacity: 0.15,
      depthTest: !1,
      depthWrite: !1
    });
    this._colliderMesh = new p(l, _), this._colliderMesh.visible = !1, this._colliderMesh.layers.mask = d, this._colliderMesh.renderOrder = 1 / 0, this._colliderMesh.rotateX(Math.PI / 2), this._colliderMesh.translateY(e / 2), this.add(this._colliderMesh), this.rotateX(n.y * -Math.PI / 2), this.rotateY(n.x * Math.PI / 2);
  }
  set debug(t) {
    this._colliderMesh.visible = t;
  }
  get highlight() {
    return this._highlight;
  }
  set highlight(t) {
    this._highlight = t, this._lineMaterial.color = this._highlight || this._hovered ? this._colorHover : this._color;
  }
  get forwardVector() {
    return new c(0, 0, 1).applyQuaternion(this.quaternion).normalize();
  }
  get rightVector() {
    return new c(1, 0, 0).applyQuaternion(this.quaternion).normalize();
  }
  get upVector() {
    return new c(0, 1, 0).applyQuaternion(this.quaternion).normalize();
  }
  reset() {
    this._lineMaterial.color = this._color;
  }
  onPointerEnter() {
    this._hovered = !0, this.parent && this.parent.onHandleHover(this, !0);
  }
  onPointerLeave() {
    this._hovered = !1, this.parent && this.parent.onHandleHover(this, !1);
  }
  onDragStart() {
    this.parent && this.parent.onHandleDragStart(this);
  }
  onDrag(t) {
    this.parent && this.parent.onHandleDrag(this, t);
  }
  onDragEnd() {
    this.parent && this.parent.onHandleDragEnd(this);
  }
}
class q extends f {
  constructor(t) {
    super();
    i(this, "_controller");
    i(this, "children");
    i(this, "_startPos");
    this.name = "DIVETranslateGizmo", this.children = [], this._startPos = null, this._controller = t, this.add(
      new M("x", 1, new c(1, 0, 0), I)
    ), this.add(
      new M("y", 1, new c(0, 1, 0), P)
    ), this.add(
      new M("z", 1, new c(0, 0, 1), V)
    );
  }
  set debug(t) {
    this.children.forEach((e) => {
      e.debug = t;
    });
  }
  reset() {
    this.children.forEach((t) => {
      t.reset();
    });
  }
  handleHighlight(t, e, n) {
    this.children.forEach((o) => {
      n ? o.highlight = o.axis === t && n : o.highlight = o.axis === t && e;
    });
  }
  onHandleHover(t, e) {
    this._startPos || this.parent && this.parent.parent && (this.parent.parent.onHover(
      "translate",
      t.axis,
      e
    ), this.handleHighlight(t.axis, e, !1));
  }
  onHandleDragStart(t) {
    if (!this.parent || !this.parent.parent) return;
    const e = this.parent.parent.object;
    e && (this._startPos = e.position.clone(), this.handleHighlight(t.axis, !0, !0));
  }
  onHandleDrag(t, e) {
    if (!this._startPos || !this.parent || !this.parent.parent || !("onChange" in this.parent.parent)) return;
    const n = e.dragDelta.clone().projectOnVector(t.forwardVector);
    this.parent.parent.onChange(
      this._startPos.clone().add(n)
    );
  }
  onHandleDragEnd(t) {
    this._startPos = null, this.handleHighlight(t.axis, !1, !1);
  }
}
class x extends f {
  constructor(t, e, n, o, a = 0.05) {
    super();
    i(this, "isHoverable", !0);
    i(this, "isDraggable", !0);
    i(this, "parent", null);
    i(this, "axis");
    i(this, "_color", new b(16711935));
    i(this, "_colorHover");
    i(this, "_hovered");
    i(this, "_highlight");
    i(this, "_lineMaterial");
    i(this, "_colliderMesh");
    i(this, "_box");
    i(this, "_boxSize");
    this.name = "DIVEScaleHandle", this.axis = t, this._color.set(o), this._colorHover = this._color.clone().multiplyScalar(2), this._hovered = !1, this._highlight = !1, this._boxSize = a;
    const h = new E(
      0.01,
      0.01,
      e - a / 2,
      13
    );
    this._lineMaterial = new g({
      color: o,
      depthTest: !1,
      depthWrite: !1
    });
    const l = new p(h, this._lineMaterial);
    l.layers.mask = d, l.renderOrder = 1 / 0, l.rotateX(Math.PI / 2), l.translateY(e / 2 - a / 4), this.add(l), this._box = new p(
      new R(a, a, a),
      this._lineMaterial
    ), this._box.layers.mask = d, this._box.renderOrder = 1 / 0, this._box.rotateX(Math.PI / 2), this._box.translateY(e - a / 2), this._box.rotateZ(n.x * Math.PI / 2), this._box.rotateX(n.z * Math.PI / 2), this.add(this._box);
    const _ = new E(
      0.1,
      0.1,
      e + a / 2,
      3
    ), D = new g({
      color: 16711935,
      transparent: !0,
      opacity: 0.15,
      depthTest: !1,
      depthWrite: !1
    });
    this._colliderMesh = new p(_, D), this._colliderMesh.visible = !1, this._colliderMesh.layers.mask = d, this._colliderMesh.renderOrder = 1 / 0, this._colliderMesh.rotateX(Math.PI / 2), this._colliderMesh.translateY(e / 2), this.add(this._colliderMesh), this.rotateX(n.y * -Math.PI / 2), this.rotateY(n.x * Math.PI / 2);
  }
  set debug(t) {
    this._colliderMesh.visible = t;
  }
  get highlight() {
    return this._highlight;
  }
  set highlight(t) {
    this._highlight = t, this._lineMaterial.color = this._highlight || this._hovered ? this._colorHover : this._color;
  }
  get forwardVector() {
    return new c(0, 0, 1).applyQuaternion(this.quaternion).normalize();
  }
  get rightVector() {
    return new c(1, 0, 0).applyQuaternion(this.quaternion).normalize();
  }
  get upVector() {
    return new c(0, 1, 0).applyQuaternion(this.quaternion).normalize();
  }
  reset() {
    this._lineMaterial.color = this._color;
  }
  update(t) {
    this._box.scale.copy(
      new c(1, 1, 1).sub(this.forwardVector).add(
        // to then add ...
        t.clone().multiply(this.forwardVector)
        // that is scaled by the forward vector again to get the forward vector as the only direction
      )
    );
  }
  onPointerEnter() {
    this._hovered = !0, this.parent && this.parent.onHoverAxis(this, !0);
  }
  onPointerLeave() {
    this._hovered = !1, this.parent && this.parent.onHoverAxis(this, !1);
  }
  onDragStart() {
    this.parent && this.parent.onAxisDragStart(this);
  }
  onDrag(t) {
    this.parent && this.parent.onAxisDrag(this, t);
  }
  onDragEnd() {
    this.parent && this.parent.onAxisDragEnd(this);
  }
}
class Q extends f {
  constructor(t) {
    super();
    i(this, "isHoverable", !0);
    i(this, "children");
    i(this, "_controller");
    i(this, "_startScale");
    this.name = "DIVEScaleGizmo", this.children = [], this._startScale = null, this._controller = t, this.add(
      new x("x", 1, new c(1, 0, 0), I)
    ), this.add(
      new x("y", 1, new c(0, 1, 0), P)
    ), this.add(
      new x("z", 1, new c(0, 0, 1), V)
    );
  }
  set debug(t) {
    this.children.forEach((e) => {
      e.debug = t;
    });
  }
  reset() {
    this.children.forEach((t) => {
      t.reset();
    });
  }
  update(t) {
    this.children.forEach((e) => {
      e.update(t);
    });
  }
  handleHighlight(t, e, n) {
    this.children.forEach((o) => {
      n ? o.highlight = o.axis === t && n : o.highlight = o.axis === t && e;
    });
  }
  onHoverAxis(t, e) {
    this._startScale || this.parent && this.parent.parent && (this.parent.parent.onHover(
      "translate",
      t.axis,
      e
    ), this.handleHighlight(t.axis, e, !1));
  }
  onAxisDragStart(t) {
    if (!this.parent || !this.parent.parent) return;
    const e = this.parent.parent.object;
    e && (this._startScale = e.scale.clone(), this.handleHighlight(t.axis, !0, !0));
  }
  onAxisDrag(t, e) {
    if (!this._startScale || !this.parent || !this.parent.parent || !("onChange" in this.parent.parent)) return;
    const n = e.dragDelta.clone().projectOnVector(t.forwardVector);
    this.parent.parent.onChange(
      void 0,
      void 0,
      this._startScale.clone().add(n)
    );
  }
  onAxisDragEnd(t) {
    this._startScale = null, this.handleHighlight(t.axis, !1, !1);
  }
}
class W extends f {
  constructor() {
    super();
    i(this, "_meshX");
    i(this, "_meshY");
    i(this, "_meshZ");
    this.name = "DIVEGizmoPlane";
    const t = new g({
      transparent: !0,
      opacity: 0.15,
      depthTest: !1,
      depthWrite: !1,
      side: 2
    }), e = new w(100, 100, 2, 2), n = t.clone();
    n.color.set(16711680), this._meshX = new p(e, n), this._meshX.layers.mask = d, this._meshX.rotateY(Math.PI / 2);
    const o = new w(100, 100, 2, 2), a = t.clone();
    a.color.set(65280), this._meshY = new p(o, a), this._meshY.layers.mask = d, this._meshY.rotateX(-Math.PI / 2);
    const h = new w(100, 100, 2, 2), l = t.clone();
    l.color.set(255), this._meshZ = new p(h, l), this._meshZ.layers.mask = d;
  }
  get XPlane() {
    return this._meshX;
  }
  get YPlane() {
    return this._meshY;
  }
  get ZPlane() {
    return this._meshZ;
  }
  assemble(t, e) {
    if (this.clear(), t === "translate" || t === "scale")
      switch (e) {
        case "x":
          this.add(this._meshY), this.add(this._meshZ);
          break;
        case "y":
          this.add(this._meshX), this.add(this._meshZ);
          break;
        case "z":
          this.add(this._meshX), this.add(this._meshY);
          break;
      }
    else if (t === "rotate")
      switch (e) {
        case "x":
          this.add(this._meshX);
          break;
        case "y":
          this.add(this._meshY);
          break;
        case "z":
          this.add(this._meshZ);
          break;
      }
  }
}
class J extends f {
  constructor(t) {
    super();
    i(this, "_mode");
    i(this, "_gizmoNode");
    i(this, "_translateGizmo");
    i(this, "_rotateGizmo");
    i(this, "_scaleGizmo");
    i(this, "_gizmoPlane");
    // attachment stuff
    i(this, "_object");
    this.name = "DIVEGizmo", t.addEventListener("change", () => {
      const e = t.getDistance() / 2.5;
      this.scale.set(e, e, e);
    }), this._mode = "translate", this._gizmoNode = new f(), this.add(this._gizmoNode), this._translateGizmo = new q(t), this._rotateGizmo = new Z(t), this._scaleGizmo = new Q(t), this._gizmoPlane = new W(), this._gizmoPlane.visible = !1, this._object = null;
  }
  get mode() {
    return this._mode;
  }
  set mode(t) {
    this._mode = t, this.assemble();
  }
  set debug(t) {
    this._translateGizmo.debug = t, this._rotateGizmo.debug = t, this._scaleGizmo.debug = t;
  }
  get gizmoNode() {
    return this._gizmoNode;
  }
  get gizmoPlane() {
    return this._gizmoPlane;
  }
  get object() {
    return this._object;
  }
  attach(t) {
    return this._object = t, this.assemble(), this;
  }
  detach() {
    return this._object = null, this.assemble(), this;
  }
  onHover(t, e, n) {
    n && this._gizmoPlane.assemble(t, e);
  }
  onChange(t, e, n) {
    this.object !== null && (t && (this.position.copy(t), this.object.position.copy(t)), e && this.object.rotation.copy(e), n && (this.object.scale.copy(n), this._scaleGizmo.update(n)));
  }
  assemble() {
    this._gizmoNode.clear(), this._gizmoPlane.clear(), this._translateGizmo.reset(), this._rotateGizmo.reset(), this._scaleGizmo.reset(), this.object !== null && (this._mode === "translate" && this._gizmoNode.add(this._translateGizmo), this._mode === "rotate" && this._gizmoNode.add(this._rotateGizmo), this._mode === "scale" && this._gizmoNode.add(this._scaleGizmo), this.add(this._gizmoPlane));
  }
}
class $ {
  constructor() {
    i(this, "isMovable", !0);
  }
}
class tt {
  constructor() {
    i(this, "isSelectable", !0);
  }
}
function et(r, s) {
  return s.forEach((t) => {
    Object.getOwnPropertyNames(t.prototype).forEach((n) => {
      if (n === "constructor")
        return;
      const o = Object.getOwnPropertyDescriptor(
        t.prototype,
        n
      );
      Object.defineProperty(r.prototype, n, o);
    });
    const e = new t();
    Object.getOwnPropertyNames(e).forEach((n) => {
      const o = Object.getOwnPropertyDescriptor(
        e,
        n
      );
      Object.defineProperty(r.prototype, n, o);
    });
  }), r;
}
function m(r, s = /* @__PURE__ */ new WeakMap()) {
  if (r === null || typeof r != "object")
    return r;
  if (s.has(r))
    return s.get(r);
  if (r instanceof Date)
    return new Date(r.getTime());
  if (r instanceof RegExp)
    return new RegExp(r.source, r.flags);
  if (Array.isArray(r)) {
    const a = [];
    s.set(r, a);
    for (let h = 0; h < r.length; h++)
      a[h] = m(r[h], s);
    return a;
  }
  if (r instanceof Map) {
    const a = /* @__PURE__ */ new Map();
    s.set(r, a);
    for (const [
      h,
      l
    ] of r)
      a.set(m(h, s), m(l, s));
    return a;
  }
  if (r instanceof Set) {
    const a = /* @__PURE__ */ new Set();
    s.set(r, a);
    for (const h of r)
      a.add(m(h, s));
    return a;
  }
  const t = r;
  if (typeof t.clone == "function") {
    const a = t.clone();
    return s.set(r, a), a;
  }
  const e = Object.create(Object.getPrototypeOf(r));
  s.set(r, e);
  const n = Object.getOwnPropertyNames(r);
  for (const a of n) {
    const h = Object.getOwnPropertyDescriptor(r, a);
    if (h)
      if (h.value !== void 0) {
        const l = m(h.value, s);
        Object.defineProperty(e, a, {
          ...h,
          value: l
        });
      } else
        Object.defineProperty(e, a, h);
  }
  const o = Object.getOwnPropertySymbols(r);
  for (const a of o) {
    const h = Object.getOwnPropertyDescriptor(r, a);
    if (h)
      if (h.value !== void 0) {
        const l = m(h.value, s);
        Object.defineProperty(e, a, {
          ...h,
          value: l
        });
      } else
        Object.defineProperty(e, a, h);
  }
  return e;
}
const z = (r, s) => {
  if (Object.keys(r).length === 0 && Object.keys(s).length === 0)
    return {};
  if (typeof r != "object" || typeof s != "object")
    return s;
  let t = {};
  return Object.keys(s).forEach((e) => {
    if (!Object.keys(r).includes(e)) {
      t = { ...t, [e]: s[e] };
      return;
    }
    if (Array.isArray(s[e])) {
      if (!Array.isArray(r[e])) {
        t = { ...t, [e]: s[e] };
        return;
      }
      const n = r[e], o = s[e];
      if (n.length === 0 && o.length === 0) {
        t = { ...t };
        return;
      }
      if (n.length !== o.length) {
        t = { ...t, [e]: s[e] };
        return;
      }
      const a = [];
      if (o.forEach((h, l) => {
        const _ = z(
          n[l],
          o[l]
        );
        Object.keys(_).length && a.push(o[l]);
      }), Object.keys(a).length) {
        t = { ...t, [e]: a };
        return;
      }
      return;
    }
    if (typeof s[e] == "object") {
      if (typeof r[e] != "object") {
        t = { ...t, [e]: s[e] };
        return;
      }
      const n = z(
        r[e],
        s[e]
      );
      if (Object.keys(n).length) {
        t = { ...t, [e]: n };
        return;
      }
    }
    r[e] !== s[e] && (t = { ...t, [e]: s[e] });
  }), t;
};
function rt(r) {
  return r.entityType === "group";
}
function st(r) {
  return r.entityType === "light";
}
function it(r) {
  return r.entityType === "primitive";
}
export {
  V as AxesColorBlue,
  Zt as AxesColorBlueLetter,
  P as AxesColorGreen,
  qt as AxesColorGreenLetter,
  I as AxesColorRed,
  Qt as AxesColorRedLetter,
  ot as BoundingBox,
  Yt as COORDINATE_LAYER_MASK,
  jt as DEFAULT_LAYER_MASK,
  ht as DIVE,
  lt as DIVEAmbientLight,
  ct as DIVEClock,
  dt as DIVEDefaultSettings,
  pt as DIVEEngine,
  ut as DIVEEnvironment,
  ft as DIVEEnvironmentDefaultSettings,
  _t as DIVEFloor,
  J as DIVEGizmo,
  gt as DIVEGrid,
  mt as DIVEGroup,
  F as DIVEMath,
  Et as DIVEModel,
  $ as DIVEMovable,
  Dt as DIVENode,
  Ct as DIVEPerspectiveCamera,
  Lt as DIVEPerspectiveCameraDefaultSettings,
  wt as DIVEPointLight,
  yt as DIVEPrimitive,
  Mt as DIVERenderPipeline,
  xt as DIVERenderer,
  It as DIVERendererDefaultSettings,
  Pt as DIVEResizeManager,
  Vt as DIVERoot,
  bt as DIVEScene,
  Ht as DIVESceneDefaultSettings,
  zt as DIVESceneLight,
  tt as DIVESelectable,
  vt as DIVEView,
  Ot as FILE_TYPES,
  te as FileContentError,
  se as FileTypeError,
  St as GRID_CENTER_LINE_COLOR,
  At as GRID_SIDE_LINE_COLOR,
  Nt as HELPER_LAYER_MASK,
  ee as NetworkError,
  Xt as PRODUCT_LAYER_MASK,
  ie as ParseError,
  Gt as SUPPORTED_FILE_TYPES,
  d as UI_LAYER_MASK,
  et as applyMixins,
  m as deepClone,
  kt as findInterface,
  Rt as findSceneRecursive,
  Kt as getFileTypeFromUri,
  z as getObjectDelta,
  Ut as implementsInterface,
  Jt as isFileTypeSupported,
  rt as isGroupSchema,
  st as isLightSchema,
  ae as isModelSchema,
  oe as isPovSchema,
  it as isPrimitiveSchema
};
