import Ajv from "ajv";
import addFormats from "ajv-formats";
import type {
  Analyst,
  ApiErrorEnvelope,
  LookupView,
  QuotaView,
} from "./api.generated";
import analystSchema from "./schemas/analyst.schema.json";
import apiErrorSchema from "./schemas/api-error.schema.json";
import enumsSchema from "./schemas/enums.schema.json";
import lookupViewSchema from "./schemas/lookup-view.schema.json";
import quotaViewSchema from "./schemas/quota-view.schema.json";

// Same JSON Schemas the API's integration tests enforce (contracts/schemas): a response that
// drifts from the contract is rejected here instead of silently corrupting UI state.
const ajv = new Ajv({ allErrors: false, strict: false });
addFormats(ajv);
ajv.addSchema(enumsSchema);

export const isLookupView = ajv.compile<LookupView>(lookupViewSchema);
export const isQuotaView = ajv.compile<QuotaView>(quotaViewSchema);
export const isAnalyst = ajv.compile<Analyst>(analystSchema);
export const isApiErrorEnvelope = ajv.compile<ApiErrorEnvelope>(apiErrorSchema);
