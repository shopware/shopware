{ pkgs ? import <nixpkgs> {}}:

pkgs.mkShell {
  nativeBuildInputs = [ pkgs.nodejs-14_x ];
}
