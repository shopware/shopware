declare module "*.md" {
    import type { SkillReference } from "@flue/runtime";

    const skill: SkillReference;

    export default skill;
}
