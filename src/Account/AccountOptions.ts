import {
  ISlideOptionsContext,
  SlideOptionsModule, VueInstance
} from "@comeen/comeen-play-sdk-js";

export default class AccountOptionsModule extends SlideOptionsModule {
  constructor(context: ISlideOptionsContext) {
    super(context);
  }

  async onReady() {
    return true;
  };

  setup(props: Record<string, any>, vue: VueInstance, context: ISlideOptionsContext) {
    const { h } = vue;

    const update = context.update;

    const { Field, TextInput, Toggle } = this.context.components

    return () =>
      h("div", {}, [
        h(Field, { label: this.t("modules.simpplr-account.options.user_email") },
          h(TextInput, { ...update.option('user_email'), placeholder: 'user@mail.com' })
        ),
        // h(Field, { label: this.t("modules.lumapps-microsoft-account.options.host") },
        //   h(TextInput, { ...update.option('host'), placeholder: '' })
        // ),
        // h(Field, { label: this.t("modules.lumapps-microsoft-account.options.organization_id") },
        //   h(TextInput, { ...update.option('organization_id'), placeholder: '' })
        // ),
        // h(Field, { label: this.t("modules.lumapps-microsoft-account.options.client_id") },
        //   h(TextInput, { ...update.option('client_id'), placeholder: '' })
        // ),
        // h(Field, { label: this.t("modules.lumapps-microsoft-account.options.client_secret") },
        //   h(TextInput, { ...update.option('client_secret'), placeholder: '' })
        // )
      ]
    )
  }
}
