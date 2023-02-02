{ pkgs ? import <nixpkgs> {}}:

pkgs.mkShell {
  nativeBuildInputs = [ pkgs.nodejs-16_x ];
}
